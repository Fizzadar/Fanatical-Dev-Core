<?php
	if( !defined( 'C_CORE_VER' ) ) die( 'Error: the core is missing' );
	
/**
 * This class provides a simple interface for OpenID (1.1 and 2.0) authentication.
 * Supports Yadis discovery.
 * The autentication process is stateless/dumb.
 *
 * Usage:
 * Sign-on with OpenID is a two step process:
 * Step one is authentication with the provider:
 * <code>
 * $openid = new LightOpenID;
 * $openid->identity = 'ID supplied by user';
 * header('Location: ' . $openid->authUrl());
 * </code>
 * The provider then sends various parameters via GET, one of them is openid_mode.
 * Step two is verification:
 * <code>
 * if($_GET['openid_mode']) {
 *     $openid = new LightOpenID;
 *     echo $openid->validate() ? 'Logged in.' : 'Failed';
 * }
 * </code>
 *
 * Optionally, you can set $returnUrl and $realm (or $trustRoot, which is an alias).
 * The default values for those are:
 * $openid->realm     = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
 * $openid->returnUrl = $openid->realm . $_SERVER['REQUEST_URI'];
 * If you don't know their meaning, refer to any openid tutorial, or specification. Or just guess.
 *
 * AX and SREG extensions are supported.
 * To use them, specify $openid->required and/or $openid->optional.
 * These are arrays, with values being AX schema paths (the 'path' part of the URL).
 * For example:
 *   $openid->required = array('namePerson/friendly', 'contact/email');
 *   $openid->optional = array('namePerson/first');
 * If the server supports only SREG or OpenID 1.1, these are automaticaly
 * mapped to SREG names, so that user doesn't have to know anything about the server.
 *
 * To get the values, use $openid->getAttributes().
 *
 *
 * The library depends on curl, and requires PHP 5.
 * @author Mewp
 * @copyright Copyright (c) 2010, Mewp
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 */
class LightOpenID
{
    public $returnUrl
         , $required = array()
         , $optional = array();
    private $identity;
    protected $server, $version, $trustRoot, $aliases, $identifier_select = false;
    static protected $ax_to_sreg = array(
        'namePerson/friendly'     => 'nickname',
        'contact/email'           => 'email',
        'namePerson'              => 'fullname',
        'birthDate'               => 'dob',
        'person/gender'           => 'gender',
        'contact/postalCode/home' => 'postcode',
        'contact/country/home'    => 'country',
        'pref/language'           => 'language',
        'pref/timezone'           => 'timezone',
        );

    function __construct()
    {
        $this->trustRoot = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $this->returnUrl = $this->trustRoot . $_SERVER['REQUEST_URI'];

        if (!function_exists('curl_exec')) {
            throw new ErrorException('Curl extension is required.');
        }
    }

    function __set($name, $value)
    {
        switch($name) {
        case 'identity':
            if(stripos($value, 'http') === false && $value) $value = 'http://' . $value;
            $this->$name = $value;
            break;
        case 'trustRoot':
        case 'realm':
            $this->trustRoot = $value;
        }
    }

    function __get($name)
    {
        switch($name) {
        case 'identity':
            return $this->$name;
        case 'trustRoot':
        case 'realm':
            return $this->trustRoot;
        }
    }

    protected function request($url, $method='GET', $params=array())
    {
        $params = http_build_query($params);
        $curl = curl_init($url . ($method == 'GET' && $params ? '?' . $params : ''));
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        if($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        } elseif($method == 'HEAD') {
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_NOBODY, true);
        } else {
            curl_setopt($curl, CURLOPT_HTTPGET, true);
        }
        $response = curl_exec($curl);

        if(curl_errno($curl)) {
            throw new ErrorException(curl_error($curl), curl_errno($curl));
        }

        return $response;
    }

    protected function build_url($url, $parts)
    {
        if(isset($url['query'], $parts['query'])) {
            $parts['query'] = $url['query'] . '&' . $parts['query'];
        }

        $url = $parts + $url;
        $url = $url['scheme'] . '://'
             . (empty($url['username'])?''
                 :(empty($url['password'])? "{$url['username']}@"
                 :"{$url['username']}:{$url['password']}@"))
             . $url['host']
             . (empty($url['port'])?'':":{$url['port']}")
             . (empty($url['path'])?'':$url['path'])
             . (empty($url['query'])?'':"?{$url['query']}")
             . (empty($url['fragment'])?'':":{$url['fragment']}");
        return $url;
    }

    protected function htmlTag($content, $tag, $attrName, $attrValue, $valueName)
    {
        preg_match_all("#<{$tag}[^>]*$attrName=['\"].*?$attrValue.*?['\"][^>]*$valueName=['\"](.+?)['\"][^>]*/?>#i", $content, $matches1);
        preg_match_all("#<{$tag}[^>]*$attrName=['\"].*?$attrValue.*?['\"][^>]*$valueName=['\"](.+?)['\"][^>]*/?>#i", $content, $matches1);
        preg_match_all("#<{$tag}[^>]*$valueName=['\"](.+?)['\"][^>]*$attrName=['\"].*?$attrValue.*?['\"][^>]*/?>#i", $content, $matches2);

        $result = array_merge($matches1[1], $matches2[1]);
        return empty($result)?false:$result[0];
    }

    /**
     * Performs Yadis and HTML discovery. Normally not used.
     * @param $url Identity URL.
     * @return String OP Endpoint (i.e. OpenID provider address).
     * @throws ErrorException
     */
    function discover($url)
    {
        if(!$url) throw new ErrorException('No identity supplied.');
        # We save the original url in case of Yadis discovery failure.
        # It can happen when we'll be lead to an XRDS document
        # which does not have any OpenID2 services.
        $originalUrl = $url;

        # A flag to disable yadis discovery in case of failure in headers.
        $yadis = true;

        # We'll jump a maximum of 5 times, to avoid endless redirections.
        for($i = 0; $i < 5; $i ++) {
            if($yadis) {
                $headers = explode("\n",$this->request($url, 'HEAD'));

                $next = false;
                foreach($headers as $header) {
                    if(preg_match('#X-XRDS-Location\s*:\s*(.*)#', $header, $m)) {
                        $url = $this->build_url(parse_url($url), parse_url(trim($m[1])));
                        $next = true;
                    }

                    if(preg_match('#Content-Type\s*:\s*application/xrds\+xml#i', $header)) {
                        # Found an XRDS document, now let's find the server, and optionally delegate.
                        $content = $this->request($url, 'GET');

                        # OpenID 2
                        # We ignore it for MyOpenID, as it breaks sreg if using OpenID 2.0
                        $ns = preg_quote('http://specs.openid.net/auth/2.0/');
                        if (preg_match('#<Service.*?>(.*)<Type>\s*'.$ns.'(.*?)\s*</Type>(.*)</Service>#s', $content, $m)
                            && !preg_match('/myopenid\.com/i', $this->identity)) {
                            $content = $m[1] . $m[3];
                            if($m[2] == 'server') $this->identifier_select = true;

                            $content = preg_match('#<URI>(.*)</URI>#', $content, $server);
                            $content = preg_match('#<LocalID>(.*)</LocalID>#', $content, $delegate);
                            if(empty($server)) {
                                return false;
                            }
                            # Does the server advertise support for either AX or SREG?
                            $this->ax   = preg_match('#<Type>http://openid.net/srv/ax/1.0</Type>#', $content);
                            $this->sreg = preg_match('#<Type>http://openid.net/sreg/1.0</Type>#', $content);

                            $server = $server[1];
                            if(isset($delegate[1])) $this->identity = $delegate[1];
                            $this->version = 2;

                            $this->server = $server;
                            return $server;
                        }

                        # OpenID 1.1
                        $ns = preg_quote('http://openid.net/signon/1.1');
                        if(preg_match('#<Service.*?>(.*)<Type>\s*'.$ns.'\s*</Type>(.*)</Service>#s', $content, $m)) {
                            $content = $m[1] . $m[2];

                            $content = preg_match('#<URI>(.*)</URI>#', $content, $server);
                            $content = preg_match('#<.*?Delegate>(.*)</.*?Delegate>#', $content, $delegate);
                            if(empty($server)) {
                                return false;
                            }
                            # AX can be used only with OpenID 2.0, so checking only SREG
                            $this->sreg = preg_match('#<Type>http://openid.net/sreg/1.0</Type>#', $content);

                            $server = $server[1];
                            if(isset($delegate[1])) $this->identity = $delegate[1];
                            $this->version = 1;

                            $this->server = $server;
                            return $server;
                        }

                        $next = true;
                        $yadis = false;
                        $url = $originalUrl;
                        $content = null;
                        break;
                    }
                }
                if($next) continue;

                # There are no relevant information in headers, so we search the body.
                $content = $this->request($url, 'GET');
                if($location = $this->htmlTag($content, 'meta', 'http-equiv', 'X-XRDS-Location', 'value')) {
                    $url = $this->build_url(parse_url($url), parse_url($location));
                    continue;
                }
            }

            if(!$content) $content = $this->request($url, 'GET');

            # At this point, the YADIS Discovery has failed, so we'll switch
            # to openid2 HTML discovery, then fallback to openid 1.1 discovery.
            $server   = $this->htmlTag($content, 'link', 'rel', 'openid2.provider', 'href');
            $delegate = $this->htmlTag($content, 'link', 'rel', 'openid2.local_id', 'href');
            $this->version = 2;
            
            # Another hack for myopenid.com...
            if(preg_match('/myopenid\.com/i', $server)) {
                $server = null;
            }

            if(!$server) {
                # The same with openid 1.1
                $server   = $this->htmlTag($content, 'link', 'rel', 'openid.server', 'href');
                $delegate = $this->htmlTag($content, 'link', 'rel', 'openid.delegate', 'href');
                $this->version = 1;
            }

            if($server) {
                # We found an OpenID2 OP Endpoint
                if($delegate) {
                    # We have also found an OP-Local ID.
                    $this->identity = $delegate;
                }
                $this->server = $server;
                return $server;
            }

            throw new ErrorException('No servers found!');
        }
        throw new ErrorException('Endless redirection!');
    }
    protected function sregParams()
    {
        $params = array();
        if($this->required) {
            $params['openid.sreg.required'] = array();
            foreach($this->required as $required) {
                if(!isset(self::$ax_to_sreg[$required])) continue;
                $params['openid.sreg.required'][] = self::$ax_to_sreg[$required];
            }
            $params['openid.sreg.required'] = implode(',', $params['openid.sreg.required']);
        }

        if($this->optional) {
            $params['openid.sreg.optional'] = array();
            foreach($this->optional as $optional) {
                if(!isset(self::$ax_to_sreg[$optional])) continue;
                $params['openid.sreg.optional'][] = self::$ax_to_sreg[$optional];
            }
            $params['openid.sreg.optional'] = implode(',', $params['openid.sreg.optional']);
        }
        return $params;
    }
    protected function axParams()
    {
        $params = array();
        if($this->required || $this->optional) {
            $params['openid.ns.ax'] = 'http://openid.net/srv/ax/1.0';
            $params['openid.ax.mode'] = 'fetch_request';
            $this->aliases  = array();
            $counts   = array();
            $required = array();
            $optional = array();
            foreach(array('required','optional') as $type) {
                foreach($this->$type as $alias => $field) {
                    if(is_int($alias)) $alias = strtr($field, '/', '_');
                    $this->aliases[$alias] = 'http://axschema.org/' . $field;
                    if(empty($counts[$alias])) $counts[$alias] = 0;
                    $counts[$alias] += 1;
                    ${$type}[] = $alias;
                }
            }
            foreach($this->aliases as $alias => $ns) {
                $params['openid.ax.type.' . $alias] = $ns;            }
            foreach($counts as $alias => $count) {
                if($count == 1) continue;
                $params['openid.ax.count.' . $alias] = $count;
            }
            $params['openid.ax.required'] = implode(',', $required);
            $params['openid.ax.if_avaiable'] = implode(',', $optional);
        }
        return $params;
    }

    protected function authUrl_v1()
    {
        $params = array(
            'openid.return_to'  => $this->returnUrl,
            'openid.mode'       => 'checkid_setup',
            'openid.identity'   => $this->identity,
            'openid.trust_root' => $this->trustRoot,
            ) + $this->sregParams();

        return $this->build_url(parse_url($this->server)
                               , array('query' => http_build_query($params)));
    }

    protected function authUrl_v2($identifier_select)
    {
        $params = array(
            'openid.ns'          => 'http://specs.openid.net/auth/2.0',
            'openid.mode'        => 'checkid_setup',
            'openid.return_to'   => $this->returnUrl,
            'openid.realm'       => $this->trustRoot,
        );
        if($this->ax) {
            $params += $this->axParams();
        } if($this->sreg) {
            $params += $this->sregParams();
        } else {
            # If OP doesn't advertise either SREG, nor AX, let's send them both
            # in worst case we don't get anything in return.
            $params += $this->axParams() + $this->sregParams();
        }
        if($identifier_select) {
            $params['openid.identity'] = $params['openid.claimed_id']
                 = 'http://specs.openid.net/auth/2.0/identifier_select';
        } else {
            $params['openid.identity'] = $params['openid.claimed_id'] = $this->identity;
        }

        return $this->build_url(parse_url($this->server)
                               , array('query' => http_build_query($params)));
    }

    /**
     * Returns authentication url. Usually, you want to redirect your user to it.
     * @return String The authentication url.
     * @param String $select_identifier Whether to request OP to select identity for an user in OpenID 2. Does not affect OpenID 1.
     * @throws ErrorException
     */
    function authUrl($identifier_select = null)
    {
        if(!$this->server) $this->Discover($this->identity);

        if($this->version == 2) {
            if($identifier_select === null) {
                return $this->authUrl_v2($this->identifier_select);
            }
            return $this->authUrl_v2($identifier_select);
        }
        return $this->authUrl_v1();
    }

    /**
     * Performs OpenID verification with the OP.
     * @return Bool Whether the verification was successful.
     * @throws ErrorException
     */
    function validate()
    {
        $params = array(
            'openid.assoc_handle' => $_GET['openid_assoc_handle'],
            'openid.signed'       => $_GET['openid_signed'],
            'openid.sig'          => $_GET['openid_sig'],
            );

        if(isset($_GET['openid_op_endpoint'])) {
            # We're dealing with an OpenID 2.0 server, so let's set an ns
            # Even though we should know location of the endpoint,
            # we still need to verify it by discovery, so $server is not set here
            $params['openid.ns'] = 'http://specs.openid.net/auth/2.0';
        }
        $server = $this->discover($_GET['openid_identity']);

        foreach(explode(',', $_GET['openid_signed']) as $item) {
            $params['openid.' . $item] = $_GET['openid_' . str_replace('.','_',$item)];
        }

        $params['openid.mode'] = 'check_authentication';

        $response = $this->request($server, 'POST', $params);

        return preg_match('/is_valid\s*:\s*true/i', $response);
    }
    protected function getAxAttributes()
    {
        $alias = null;
        if (isset($_GET['openid_ns_ax'])
            && $_GET['openid_ns_ax'] != 'http://openid.net/srv/ax/1.0'
        ) { # It's the most likely case, so we'll check it before
            $alias = 'ax';
        } else {
            # 'ax' prefix is either undefined, or points to another extension,
            # so we search for another prefix
            foreach($_GET as $key => $val) {
                if (substr($key, 0, strlen('openid_ns_')) == 'openid_ns_'
                    && $val == 'http://openid.net/srv/ax/1.0'
                ) {
                    $alias = substr($key, strlen('openid_ns_'));
                    break;
                }
            }
        }
        if (!$alias) {
            # An alias for AX schema has not been found,
            # so there is no AX data in the OP's response
            return array();
        }

        foreach ($_GET as $key => $value) {
            $keyMatch = 'openid_' . $alias . '_value_';
            if(substr($key, 0, strlen($keyMatch)) != $keyMatch) {
                continue;
            }
            $key = substr($key, strlen($keyMatch));
            if(!isset($_GET['openid_' . $alias . '_type_' . $key])) {
                # OP is breaking the spec by returning a field without
                # associated ns. This shouldn't happen, but it's better
                # to check, than cause an E_NOTICE.
                continue;
            }
            $key = substr($_GET['openid_' . $alias . '_type_' . $key],
                          strlen('http://axschema.org/'));
            $attributes[$key] = $value;
        }
        # Found the AX attributes, so no need to scan for SREG.
        return $attributes;
    }
    protected function getSregAttributes()
    {
        $attributes = array();
        $sreg_to_ax = array_flip(self::$ax_to_sreg);
        foreach ($_GET as $key => $value) {
            $keyMatch = 'openid_sreg_';
            if(substr($key, 0, strlen($keyMatch)) != $keyMatch) {
                continue;
            }
            $key = substr($key, strlen($keyMatch));
            if(!isset($sreg_to_ax[$key])) {
                # The field name isn't part of the SREG spec, so we ignore it.
                continue;
            }
            $attributes[$sreg_to_ax[$key]] = $value;
        }
        return $attributes;
    }
    /**
     * Gets AX/SREG attributes provided by OP. should be used only after successful validaton.
     * Note that it does not guarantee that any of the required/optional parameters will be present,
     * or that there will be no other attributes besides those specified.
     * In other words. OP may provide whatever information it wants to.
     *     * SREG names will be mapped to AX names.
     *     * @return Array Array of attributes with keys being the AX schema names, e.g. 'contact/email'
     * @see http://www.axschema.org/types/
     */
    function getAttributes()
    {
        $attributes;
        if (isset($_GET['openid_ns'])
            && $_GET['openid_ns'] == 'http://specs.openid.net/auth/2.0'
        ) { # OpenID 2.0
            # We search for both AX and SREG attributes, with AX taking precedence.
            return $this->getAxAttributes() + $this->getSregAttributes();
        }
        return $this->getSregAttributes();
    }
}
?>