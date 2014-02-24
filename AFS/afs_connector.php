<?php
require_once 'COMMON/afs_connector_interface.php';
require_once 'COMMON/afs_connector_base.php';
require_once 'COMMON/afs_exception.php';
require_once 'AFS/afs_exception.php';


/** @brief Base class for all AFS web services.
 *
 * Base class for Search, ACP... web services.
 *
 * Derived class of this class should implement get_web_service_name method.
 */
abstract class AfsConnector extends AfsConnectorBase implements AfsConnectorInterface
{
    protected $url = null; ///> URL generated to query AFS web service (debug purpose).
    private $associative_array = false;

    /** @brief Retrieves web service name.
     *
     * Derived classes must implement this method.
     * @return name of the web service which should be queried.
     */
    protected function get_web_service_name()
    {
        throw new AfsNotImplementedException();
    }
    /** @brief While decoding JSON stream, data can be decoded as associative array.
     *
     * By default, returned object are not converted to associate arrays.
     *
     * @param $value [in] @c true (default) to convert object to associative
     *        arrays, @c false to preserve objects as is.
     */
    protected function build_reply_as_associative_array($value=true)
    {
        $this->associative_array = $value;
    }

    /** @brief Retrieves the URL generated by previous call to send method.
     *
     * This URL can be used as debug purpose.
     * @return Generated URL or @c null when send has not yet been called.
     */
    public function get_generated_url()
    {
        return $this->url;
    }

    /** @internal
     * @brief Builds URL to query Antidot web service.
     *
     * @param $web_service [in] Name of the web service to use (no check is done).
     * @param $parameters [in] List of parameters to provide to the web service.
     *
     * @return constructed URL.
     */
    protected function build_url($web_service, array $parameters)
    {
        $this->update_with_defaults($parameters);
        $this->url = sprintf('%s://%s/%s?%s', $this->scheme, $this->host, $web_service,
            $this->format_parameters($parameters));
        return $this->url;
    }

    /** @internal
     * @brief Update provided parameters with standard ones.
     * @param $parameters [in-out] List of parameters to update with standard parameters.
     */
    protected function update_with_defaults(array& $parameters)
    {
        $parameters['afs:service'] = $this->service->id;
        $parameters['afs:status'] = $this->service->status;
        $parameters['afs:output'] = 'json,2';
        $parameters['afs:log'] = get_api_version();
        if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
            $parameters['afs:ip'] = $_SERVER['REMOTE_ADDR'];
        }
        if (array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
            $parameters['afs:userAgent'] = $_SERVER['HTTP_USER_AGENT'];
        }
    }

    /** @brief Sends a query.
     *
     * Query is built using provided @a parameters.
     * @param $parameters [in] list of parameters used to build the query.
     * @return JSON decoded reply of the query.
     */
    public function send(array $parameters)
    {
        $this->url = $this->build_url($this->get_web_service_name(), $parameters);
        $request = curl_init($this->url);
        if ($request == false) {
            $result = $this->build_error('Cannot initialize connexion', $this->url);
        } else {
            curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
            //curl_setopt($request, CURLOPT_FAILONERROR, true);
            curl_setopt($request, CURLOPT_HTTPHEADER, $this->get_http_header());

            $result = curl_exec($request);
            curl_close($request);
            try {
                if ($result == false)
                    throw new AfsConnectorExecutionFailedException();
                $result = json_decode($result, $this->associative_array);
                if (empty($result))
                    throw new AfsConnectorExecutionFailedException();
            } catch (AfsConnectorExecutionFailedException $e) {
                $result = $this->build_error('Failed to execute request',  $this->url);
            }
        }
        return $result;
    }

    private function get_http_header()
    {
        $header = array();
        if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
            if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
                $header[] = 'X-Forwarded-For: ' . $_SERVER['HTTP_X_FORWARDED_FOR']
                        . ', ' . $_SERVER['REMOTE_ADDR'];
            } else {
                $header[] = 'X-Forwarded-For: ' . $_SERVER['REMOTE_ADDR'];
            }
        }

        if (array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
            $header[] = 'User-Agent: ' . $_SERVER['HTTP_USER_AGENT'];
        }
        return $header;
    }

    protected function build_error($message, $details)
    {
        //error_log("$message [$details]");
        return json_decode('{ "header": { "error": { "message": [ "' . $message . '" ] } } }',
                           $this->associative_array);
    }
}
