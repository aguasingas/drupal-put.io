<?php
namespace {
  use Putio\File;

  class Putio {

    protected $baseUrl = "https://api.put.io/v2/";

    /**
     * Adds access token to request parameters.
     * @param array $parameters
     * @return array
     */
    function addAccessToken($parameters = array()) {
      $access_token = variable_get('putio_access_token', '');
      $parameters['oauth_token'] = $access_token;
      return $parameters;
    }


    function doRequest($query, $parameters = array()){
      $url = $this->baseUrl . $query;
      $parameters = $this->addAccessToken($parameters);
      $url = url($url, array('query' => $parameters, 'absolute' => TRUE));
      $result = drupal_http_request($url);

      if ($result->code == 200) {
        $data = json_decode($result->data);
        return $data;
      }
      return null;
    }

    function getFilesList($parent_id = 0){
      $query = "files/list";
      $parameters = array(
        'parent_id' => $parent_id,
      );
      $data = $this->doRequest($query, $parameters);
      if ($this->requestIsOK($data)) {
        return $data->files;
      }
    }

    function getFilesSearch($search_query = '', $operators = array()){
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
      $result = $this->doRequest('files/search/' . $search_query);
      $output = array();
      if (!empty($result->files)) {
        foreach ($result->files as $file) {
          $output[] = new File($file);
        }
      }
      return $output;
    }

    function requestIsOK($data) {
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
  }
}


