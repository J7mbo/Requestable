<?php
/**
 * cURL client
 *
 * PHP version 5.4
 *
 * @category   Requestable
 * @package    Network
 * @subpackage Client
 * @author     Pieter Hordijk <info@pieterhordijk.com>
 * @copyright  Copyright (c) 2013 Pieter Hordijk
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    1.0.0
 */
namespace Requestable\Network\Client;

use Requestable\Data\Request;

/**
 * cURL client
 *
 * @category   Requestable
 * @package    Network
 * @subpackage Client
 * @author     Pieter Hordijk <info@pieterhordijk.com>
 */
class Curl implements Client
{
    /**
     * @var string The URI to make the request to
     */
    private $uri;

    /**
     * @var string The method of the request to make
     */
    private $method;

    /**
     * @var boolean Do we need to automatically follow redirects
     */
    private $redirects;

    /**
     * @var array The optional headers of the request to make
     */
    private $headers = [];

    /**
     * @var string The body of the request to make
     */
    private $body;

    /**
     * Creates instance
     *
     * @param \Requestable\Data\Request The form data
     */
    public function __construct(Request $request)
    {
        $this->uri       = $request->getUri();
        $this->method    = $request->getMethod();
        $this->redirects = $request->redirectsEnabled();
        $this->headers   = $request->getHeaders();
        $this->body      = $request->getBody();

        if ($this->body) {
            $this->headers['content-length'] = [strlen($this->body)];
        }
    }

    /**
     * Makes the request to the external service
     *
     * @returns array The headers and body of the response
     * @throws \Requestable\Network\Client\CurlException When the request failed
     */
    public function run()
    {
        if (!$client = curl_init($this->uri)) {
            throw new CurlException('Could not initialize cURL');
        }

        $this->setOptions($client);

        if (!$result = curl_exec($client)) {
            throw new CurlException('Making request failed: ' . curl_error($client));
        }

        $headerInfo = curl_getinfo($client, CURLINFO_HEADER_OUT);
        list($header, $body) = preg_split('/\r?\n\r?\n/', curl_exec($client), 2);

        if (!preg_match('#^HTTP/1\.[01] (\d{3}) ([^\r\n]+)#', $header)) {
            throw new CurlException('The HTTP response was invalid');
        }

        return [
            'header' => $header,
            'body'   => $body,
        ];
    }

    /**
     * Sets the cURL options
     *
     * @param resource $client The resource handler of cURL
     *
     * @throws \Requestable\Network\Client\CurlException When setting the cURL option failed
     */
    private function setOptions($client)
    {
        $options = [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FAILONERROR    => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLINFO_HEADER_OUT    => true,
            CURLOPT_FOLLOWLOCATION => $this->redirects,
            CURLOPT_CUSTOMREQUEST  => $this->method,
        ];

        if ($this->headers) {
            $options[CURLOPT_HTTPHEADER] = $this->getHeaders();
        }

        if ($this->body) {
            $options[CURLOPT_POSTFIELDS] = $this->body;
        }

        foreach ($options as $option => $value) {
            if (!curl_setopt($client, $option, $value)) {
                throw new CurlException('Could not set cURL option: ' . curl_error($client));
            }
        }
    }

    /**
     * Gets the headers of the request to make
     *
     * @return array The headers
     */
    private function getHeaders()
    {
        $headers = [];
        foreach ($this->headers as $name => $vals) {
            $name = preg_replace_callback('/(?:^|-)[a-z]/', function($match) { return strtoupper($match[0]); }, $name);
            foreach ($vals as $val) {
                $headers[] = $name . ': ' . trim($val);
            }
        }

        return $headers;
    }
}
