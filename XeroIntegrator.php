<?php
/*
 * Created on February 01, 2014
 * @author - Mohammad Faisal Ahmed <faisal.ahmed0001@gmail.com>
 * 
 */

abstract class XeroIntegrator
{
    private $xeroApiUrl;
    private $xeroModuleName;
    private $xeroIdentifier;
    private $xeroXmlColumn;
    private $xmlData;
    private $requestMethod;
    private $requestUriToXero;
    private $xeroResponse;
    private $xeroErrorSummary;

    public function resetWithDefaults()
    {
        $this->setXeroApiUrl('https://api.xero.com/api.xro/2.0');
        $this->setRequestMethod('GET');
        $this->setXeroErrorSummary();// = 'summarizeErrors=false';
        $this->xmlData = '';
        $this->xeroIdentifier = '';
        $this->xeroModuleName = '';
        $this->requestUriToXero = '';
        $this->xeroResponse = '';
        return true;
    }

    public function setXeroApiUrl($apiUrl)
    {
        if ($apiUrl == '') return 'XeroApiUrl cannot be empty';
        $this->xeroApiUrl = $apiUrl;
        return true;
    }

    public function setXeroErrorSummary($summary = 'false')
    {
        $this->xeroErrorSummary = $summary;
        return true;
    }

    public function setXeroModuleName($moduleName)
    {
        if ($moduleName == '') return 'XeroModuleName cannot be empty';
        $this->xeroModuleName = $moduleName;
        return true;
    }

    public function setXeroIdentifier($operationType)
    {
        if ($operationType == '') return 'xeroIdentifier cannot be empty';
        $this->xeroIdentifier = $operationType;
        return true;
    }

    public function setXeroXmlColumnNameAndValue($xmlColumn)
    {
        if (!is_array($xmlColumn)) return 'XeroXmlColumnNameAndValue must be an array';

        $this->xeroXmlColumn = $xmlColumn;

        return true;
    }

    public function setRequestMethod($requestMethod)
    {
        if ($requestMethod == '') return 'RequestMethod cannot be empty';
        $this->requestMethod = $requestMethod;
        return true;
    }

    public function getErrorSummary()
    {
        return $this->xeroErrorSummary;
    }

    public function getRequestMethod()
    {
        return $this->requestMethod;
    }

    public function getXeroApiUrl()
    {
        return $this->xeroApiUrl;
    }

    public function getXeroModuleName()
    {
        return $this->xeroModuleName;
    }

    public function getXeroIdentifier()
    {
        return $this->xeroIdentifier;
    }

    public function getXeroXmlColumn()
    {
        return $this->xeroXmlColumn;
    }

    public function getFullRequestUriToXero()
    {
        return $this->requestUriToXero;
    }

    public function getXMLData()
    {
        return $this->xmlData;
    }

    private function XMLGeneration($xmlArray)
    {
        $xmlData = '';
        foreach ($xmlArray as $key => $value) {
            if (is_array($value)) {
                $xmlData .= "<$key>" . $this->XMLGeneration($value) . "</$key>";
            } else {
                $xmlData .= "<$key>$value</$key>";
            }
        }

        return $xmlData;
    }

    protected function buildXML()
    {
        if (empty($this->xeroXmlColumn))
            return 'Please set the XML values correctly';
        if (empty($this->xeroModuleName))
            return 'Please set the Xero Module correctly';

        $xmlData = '';
        $moduleType = substr($this->xeroModuleName, 0, (strlen($this->xeroModuleName) - 1) );
        foreach ($this->xeroXmlColumn as $key => $value) {
            $xmlData .= "<$moduleType>" . $this->XMLGeneration($value) . "</$moduleType>";
        }

        $this->xmlData = "xml=<{$this->xeroModuleName}>" . $xmlData . "</{$this->xeroModuleName}>";

        return true;
    }

    protected function buildRequestUri()
    {
        if (empty($this->xeroApiUrl))
            return 'Please set the Xero URL correctly';
        else if (empty($this->xeroModuleName))
            return 'Please set the Xero Module correctly';

        $this->buildXML();

        $this->requestUriToXero = $this->xeroApiUrl . '/' . $this->xeroModuleName;
        if (isset($this->xeroIdentifier) && $this->$this->xeroIdentifier !== '') {
            $this->requestUriToXero .=  "/{$this->xeroIdentifier}";
        }
        $this->requestUriToXero .= "?summarizeErrors={$this->getErrorSummary()}";

        return true;
    }

    protected function sendCurl()
    {
        if (!isset($this->requestUriToXero) || strlen($this->requestUriToXero) == 0) return "Request URI not set";
        try {
            /* initialize curl handle */
            $ch = curl_init();
            /* set url to send request */
            curl_setopt($ch, CURLOPT_URL, $this->requestUriToXero);
            /* allow redirects */
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            /* return a response into a variable */
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            /* times out after 30s */
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            /* set http method */
            if (strtolower($this->requestMethod) === 'post') {
                curl_setopt($ch, CURLOPT_POST, 1);
                /* add POST fields parameters */
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->xmlData);
            } else if (strtolower($this->requestMethod) === 'get') {
                curl_setopt($ch, CURLOPT_HTTPGET, 1);
            }
            /* execute the cURL */
            $this->xeroResponse = curl_exec($ch);
            curl_close($ch);
        } catch (Exception $exception) {
            $this->xeroResponse = $exception;
            echo 'Exception Message: ' . $exception->getMessage() . '<br/>';
            echo 'Exception Trace: ' . $exception->getTraceAsString();
        }

        return $this->xeroResponse;
    }

    abstract public function doRequest();
}

class B2BControllerForDrupal extends XeroIntegrator
{
    public function __construct()
    {
        $this->resetWithDefaults();
    }

    public function doRequest()
    {
        if ( ($response = $this->buildRequestUri()) !== true) return $response;
        return $this->sendCurl();
    }

    public function insertRecords($moduleName, $xmlArray)
    {
        $this->resetWithDefaults();
        $this->setXeroModuleName("$moduleName");
        $this->setRequestMethod('PUT');
        if ( ($xmlSet = $this->setXeroXmlColumnNameAndValue($xmlArray)) !== true) return $xmlSet;

        return $this->doRequest();
    }

    public function getRecordsOfXero($moduleName, $id)
    {
        $this->resetWithDefaults();
        $this->setXeroModuleName("$moduleName");
        $this->setXeroIdentifier($id);

        return $this->doRequest();
    }
}
