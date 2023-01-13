<?php

namespace Behatch\HttpCall\Request;

use Behat\Mink\Driver\Goutte\Client as GoutteClient;
use Behat\Mink\Mink;
use Symfony\Component\BrowserKit\Client as BrowserKitClient;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BrowserKit
{
    protected $mink;

    public function __construct(Mink $mink)
    {
        $this->mink = $mink;
    }

    public function getMethod()
    {
        return $this->getRequest()
            ->getMethod();
    }

    public function getUri()
    {
        return $this->getRequest()
            ->getUri();
    }

    public function getServer()
    {
        return $this->getRequest()
            ->getServer();
    }

    public function getParameters()
    {
        return $this->getRequest()
            ->getParameters();
    }

    protected function getRequest()
    {
        $client = $this->mink->getSession()->getDriver()->getClient();
        // BC layer for BrowserKit 2.2.x and older
        if (method_exists($client, 'getInternalRequest')) {
            $request = $client->getInternalRequest();
        } else {
            $request = $client->getRequest();
        }

        return $request;
    }

    public function getContent()
    {
        return $this->mink->getSession()->getPage()->getContent();
    }

    public function send($method, $url, $parameters = [], $files = [], $content = null, $headers = [])
    {
        foreach ($files as $originalName => &$file) {
            if (\is_string($file)) {
                $file = new UploadedFile($file, (string) $originalName);
            }
        }

        $client = $this->mink->getSession()->getDriver()->getClient();

        $client->followRedirects(false);

        // Workaround for https://github.com/symfony/symfony/issues/33393: prevent a default Accept header to be set
        if (!isset($headers['HTTP_ACCEPT']) && '' === $client->getServerParameter('HTTP_ACCEPT')) {
            $headers['HTTP_ACCEPT'] = null;
        }

        $client->request($method, $url, $parameters, $files, $headers, $content);
        $client->followRedirects(true);
        $this->resetHttpHeaders();

        return $this->mink->getSession()->getPage();
    }

    public function setHttpHeader($name, $value): void
    {
        $client = $this->mink->getSession()->getDriver()->getClient();
        if (method_exists($client, 'setHeader')) {
            /*
             * @var \Goutte\Client $client
             */
            $client->setHeader($name, $value);
        } else {
            /**
             * @var \Symfony\Component\BrowserKit\HttpBrowser $client
             */

            /* taken from Behat\Mink\Driver\BrowserKitDriver::setRequestHeader */
            $contentHeaders = ['CONTENT_LENGTH' => true, 'CONTENT_MD5' => true, 'CONTENT_TYPE' => true];
            $name = str_replace('-', '_', strtoupper($name));

            // CONTENT_* are not prefixed with HTTP_ in PHP when building $_SERVER
            if (!isset($contentHeaders[$name])) {
                $name = 'HTTP_'.$name;
            }
            /* taken from Behat\Mink\Driver\BrowserKitDriver::setRequestHeader */

            $client->setServerParameter($name, $value);
        }
    }

    public function getHttpHeaders()
    {
        return array_change_key_case(
            $this->mink->getSession()->getResponseHeaders(),
            \CASE_LOWER
        );
    }

    public function getHttpHeader($name)
    {
        $values = $this->getHttpRawHeader($name);

        return implode(', ', $values);
    }

    public function getHttpRawHeader($name)
    {
        $name = strtolower($name);
        $headers = $this->getHttpHeaders();

        if (isset($headers[$name])) {
            $value = $headers[$name];
            if (!\is_array($headers[$name])) {
                $value = [$headers[$name]];
            }
        } else {
            throw new \OutOfBoundsException("The header '$name' doesn't exist");
        }

        return $value;
    }

    protected function resetHttpHeaders(): void
    {
        /** @var GoutteClient|BrowserKitClient $client */
        $client = $this->mink->getSession()->getDriver()->getClient();

        $client->setServerParameters([]);
        if ($client instanceof GoutteClient) {
            $client->restart();
        }
    }
}
