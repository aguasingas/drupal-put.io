<?php
namespace {

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

    function request_is_ok($data) {
      return $data->status == 'OK';
    }
  }
}
namespace Putio {
  class File extends Can_request {

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

    /**
     * Gets the contents from a folder.
     *
     * @param int $parent_id if for parent folder, defaults to home folder.
     * @return File_set
     */
    static function get_list($parent_id = 0){
      $query = "files/list";
      $parameters = array(
        'parent_id' => $parent_id,
      );
      $data = self::do_request($query, $parameters);
      if (!empty($data->files)) {
        return new File_set($data->files);
      }
    }

    static function search($search_query = '', $operators = array()) {
      $query = 'files/search/';
      if (!empty($operators)) {
        $operators_string = '';
        $default_operators = array("from", "type", "ext", "time");
        foreach ($operators as $key => $value) {
          // Remove non-valid operators.
          if (!in_array($key, $default_operators)) {
            unset($operators[$key]);
          }
          // if it's valid, add it to the query.
          else {
            $search_query .= ' ' . $key . ':' . $value;
          }
        }
      }
      $data = self::do_request($query . $search_query);
      $output = array();
      if (!empty($data->files)) {
        return new File_set($data->files);
      }
      return $output;
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

    /**
     * Renames current file.
     * @param $new_name: New name for current file.
     *
     * @see: https://put.io/v2/docs/files.html#rename
     */
    function rename($new_name) {
      $new_name = check_plain($new_name);
      $query = "files/rename";
      $parameters = array(
        'method' => 'POST',
        'file_id' => $this->id,
        'name' => $new_name,
      );
      $result = $this->do_request($query, $parameters);
    }

    /**
     * Moves a file to another parent folde.
     *
     * @param $parent_id: Id for the parent folder
     */
    function move($parent_id) {
      $query = "files/move";
      $parameters = array(
        'method' => 'POST',
        'file_ids' => $this->id,
        'parent_id' => $parent_id,
      );
      $result = $this->do_request($query, $parameters);
    }
  }

class File_set  {
  protected $files;
  function __construct($files){
    $this->populate($files);
  }
  /**
   * Populates from an array of files coming from Put.io api.
   *
   * @param $files
   */
  function populate($files) {
    foreach ($files as $file) {
      $this->files[] = new File($file);
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
  class Transfer extends Can_request {
    protected $id;
    protected $name;
    protected $file_id;
    protected $availability;
    protected $callback_url;
    protected $created_at;
    protected $created_torrent;
    protected $current_ratio;
    protected $down_speed;
    protected $downloaded;
    protected $error_message;
    protected $estimated_time;
    protected $extract;
    protected $finished_at;
    protected $is_private;
    protected $magneturi;
    protected $peers_connected;
    protected $peers_getting_from_us;
    protected $percent_done;
    protected $save_parent_id;
    protected $seconds_seeding;
    protected $size;
    protected $source;
    protected $status;
    protected $status_message;
    protected $subscription_id;
    protected $torrent_link;
    protected $tracker_message;
    protected $trackers;
    protected $type;
    protected $up_speed;
    protected $uploaded;

    function __construct($transfer) {
      $this->id = $transfer->id;
      $this->name = $transfer->name;
      $this->file_id = $transfer->file_id;
      $this->availability = $transfer->availability;
      $this->callback_url = $transfer->callback_url;
      $this->created_at = $transfer->created_at;
      $this->created_torrent = $transfer->created_torrent;
      $this->current_ratio = $transfer->current_ratio;
      $this->down_speed = $transfer->down_speed;
      $this->downloaded = $transfer->downloaded;
      $this->error_message = $transfer->error_message;
      $this->estimated_time = $transfer->estimated_time;
      $this->extract = $transfer->extract;
      $this->finished_at = $transfer->finished_at;
      $this->is_private = $transfer->is_private;
      $this->magneturi = $transfer->magneturi;
      $this->peers_connected = $transfer->peers_connected;
      $this->peers_getting_from_us = $transfer->peers_getting_from_us;
      $this->percent_done = $transfer->percent_done;
      $this->save_parent_id = $transfer->save_parent_id;
      $this->seconds_seeding = $transfer->seconds_seeding;
      $this->size = $transfer->size;
      $this->source = $transfer->source;
      $this->status = $transfer->status;
      $this->status_message = $transfer->status_message;
      $this->subscription_id = $transfer->subscription_id;
      $this->torrent_link = $transfer->torrent_link;
      $this->tracker_message = $transfer->tracker_message;
      $this->trackers = $transfer->trackers;
      $this->type = $transfer->type;
      $this->up_speed = $transfer->up_speed;
      $this->uploaded = $transfer->uploaded;
    }

    static function get_list() {
      $query = 'transfers/list';
      $data = self::do_request($query);
      $output = array();
      if (!empty($data->transfers)) {
        foreach ($data->transfers as $transfer) {
          $output[] = new Transfer($transfer);
        }
      }
      return $output;
    }
  }

  abstract class Can_request {
    function do_request($query, $parameters = array()) {
      $putio = \Putio::get_instance();
      return $putio->do_request($query, $parameters);
    }
  }
}


