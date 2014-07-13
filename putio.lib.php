<?php
namespace {
  use Putio\File;

  class Putio {

    protected $baseUrl = "https://api.put.io/v2/";

    private static $instance;

    private function __construct() { }

    public static function get_instance() {
      if ( empty( self::$instance ) ) {
        self::$instance = new \Putio();
      }
      return self::$instance;
    }

    /**
     * Adds access token to request parameters.
     * @param array $parameters
     * @return array
     */
    function add_access_token($parameters = array()) {
      $access_token = variable_get('putio_access_token', '');
      $parameters['oauth_token'] = $access_token;
      return $parameters;
    }

    /**
     * Performs a POST request to Put.io
     *
     * @param $query
     * @param array $parameters
     * @return mixed|null|string
     */
    function post_request($query, $parameters = array()) {
      // Append query to Put.io domain so we point to the right endpoint.
      $url = $this->baseUrl . $query;
      $parameters = $this->add_access_token($parameters);

      // turns the parameter array into a url-encoded, &-linked string of key-value pairs.
      $data = drupal_http_build_query($parameters);
      $options = array(
        'method' => 'POST',
        'data' => $data,
        'headers' => array('Content-Type' => 'application/x-www-form-urlencoded'),
      );
      // actual request
      $result = drupal_http_request($url, $options);
      // test if it has been successful
      if ($result->code == 200) {
        $data = json_decode($result->data);
        return $data;
      }
      return null;
    }

    /**
     * Does a request to the Put.io API
     * If it's a POST one, it delegates to post_request.
     *
     * @param $query
     * @param array $parameters
     * @return mixed|null|string
     */
    function do_request($query, $parameters = array()){
      // POST requests are processed in a different function!
      if (!empty($parameters['method']) && $parameters['method'] == 'POST') {
        // "method" parameter is only used internally to delegate to POST function,
        // so we can delete it before calling it.
        unset($parameters['method']);
        return $this->post_request($query, $parameters);
      }
      // Append query to Put.io domain.
      $url = $this->baseUrl . $query;
      $parameters = $this->add_access_token($parameters);
      $url = url($url, array('query' => $parameters, 'absolute' => TRUE));
      $result = drupal_http_request($url);

      if ($result->code == 200) {
        $data = json_decode($result->data);
        return $data;
      }
      return null;
    }

    function get_files_list($parent_id = 0){
      $query = "files/list";
      $parameters = array(
        'parent_id' => $parent_id,
      );
      $data = $this->do_request($query, $parameters);
      if ($this->request_is_ok($data)) {
        return $data->files;
      }
    }

    function get_files_search($search_query = '', $operators = array()){
      $query = "files/list";
      if (!empty($operators)) {
        $operators_string = '';
        $default_operators = array("from","type","ext","time");
        foreach ($operators as $key => $value) {
          // Remove non-valid operators.
          if (!in_array($key, $default_operators)) {
            unset($operators[$key]);
          }
          // if it's valid, add it to the query.
          else {
            $search_query  .= ' ' . $key . ':' . $value;
          }
        }
      }
      $result = $this->do_request('files/search/' . $search_query);
      $output = array();
      if (!empty($result->files)) {
        foreach ($result->files as $file) {
          $output[] = new File($file);
        }
      }
      return $output;
    }

    function request_is_ok($data) {
      return $data->status == 'OK';
    }
  }
}
namespace Putio {
  class File {

    protected $id;
    protected $name;
    protected $content_type;
    protected $crc32;
    protected $created_at;
    protected $first_accessed_at;
    protected $icon;
    protected $is_mp4_available;
    protected $is_shared;
    protected $opensubtitles_hash;
    protected $parent_id;
    protected $screenshot;
    protected $size;

    public function __construct($file) {
      $this->id                 = $file->id;
      $this->name               = $file->name;
      $this->content_type       = $file->content_type;
      $this->crc32              = $file->crc32;
      $this->created_at         = $file->created_at;
      $this->first_accessed_at  = $file->first_accessed_at;
      $this->icon               = $file->icon;
      $this->is_mp4_available   = $file->is_mp4_available;
      $this->is_shared          = $file->is_shared;
      $this->opensubtitles_hash = $file->opensubtitles_hash;
      $this->parent_id          = $file->parent_id;
      $this->screenshot         = $file->screenshot;
      $this->size               = $file->size;
    }

    // Creating magic getters for every property.
    // Since we get this info from Put.io API, we don't need/want setters.
    function __call($method, $params) {

      $var = substr($method, 4);

      if (strncasecmp($method, "get_", 4) === 0) {
        return $this->$var;
      }
    }

    function do_request($query, $parameters = array()) {
      $putio = \Putio::get_instance();
      $data = $putio->do_request($query, $parameters);
      if ($putio->request_is_ok($data)) {
        return $data;
      }
    }

    /**
     * Gets mp4 conversion status.
     *
     * @return object with properties:
     *  size - Size of the mp4 version
     *  status - status for the mp4 conversion
     *
     * @see https://put.io/v2/docs/files.html#get-mp4-status
     */
    function get_mp4_status() {
      $query = format_string("files/@id/mp4", array('@id' => $this->id));
      $data = $this->do_request($query);
      // turns string constants into numeric constant defined in mp4_status class.
      if (!empty($data->mp4)){
        switch ($data->mp4->status) {
          case 'NOT_AVAILABLE':
            $data->mp4->status = mp4_status::NOT_AVAILABLE;
            break;
          case 'IN_QUEUE':
            $data->mp4->status = mp4_status::IN_QUEUE;
            break;
          case 'CONVERTING':
            $data->mp4->status = mp4_status::CONVERTING;
            break;
          case 'COMPLETED':
            $data->mp4->status = mp4_status::COMPLETED;
            break;
        }
        return $data->mp4;
      }
    }
  }

  /**
   * Class mp4_status
   * @package Putio
   *
   * Includes constants for mp4-related status for files.
   */
  class mp4_status {
    const NOT_AVAILABLE = 0;
    const IN_QUEUE = 1;
    const CONVERTING = 2;
    const COMPLETED = 3;
  }
}



