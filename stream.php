<?php
//https://github.com/fennb/phirehose/blob/master/example/filter-track-geo.php
require_once('./lib/Phirehose.php');
require_once('./lib/OauthPhirehose.php');

spl_autoload_register(function($class){
  $path = str_replace('\\', '/', substr($class, 0));
  if (file_exists('/Users/jcreasy/code/Elastica/lib/' . $path . '.php')) {
    require_once('/Users/jcreasy/code/Elastica/lib/' . $path . '.php');
  }
});

$elasticaClient = new \Elastica\Client(array(
    'host' => 'dwh-edge001.atl1.turn.com',
    'port' => 9292
));

// Load index
$elasticaIndex = $elasticaClient->getIndex('twitter-elastica');

// Create the index new
$elasticaIndex->create(
    array(
        'number_of_shards' => 4,
        'number_of_replicas' => 1,
        'analysis' => array(
            'analyzer' => array(
                'indexAnalyzer' => array(
                    'type' => 'custom',
                    'tokenizer' => 'standard',
                    'filter' => array('lowercase', 'mySnowball')
                ),
                'searchAnalyzer' => array(
                    'type' => 'custom',
                    'tokenizer' => 'standard',
                    'filter' => array('standard', 'lowercase', 'mySnowball')
                )
            ),
            'filter' => array(
                'mySnowball' => array(
                    'type' => 'snowball',
                    'language' => 'German'
                )
            )
        )
    ),
    true
);

//Create a type
$elasticaType = $elasticaIndex->getType('tweet');

// Define mapping
$mapping = new \Elastica\Type\Mapping();
$mapping->setType($elasticaType);
$mapping->setParam('index_analyzer', 'indexAnalyzer');
$mapping->setParam('search_analyzer', 'searchAnalyzer');

// Define boost field
$mapping->setParam('_boost', array('name' => '_boost', 'null_value' => 1.0));

// Set mapping
$mapping->setProperties(array(
    'id'      => array('type' => 'string', 'include_in_all' => FALSE),
    'user'    => array(
        'type' => 'object',
        'properties' => array(
            'name'      => array('type' => 'string', 'include_in_all' => TRUE),
            'sreen_name'      => array('type' => 'string', 'include_in_all' => TRUE),
            'fullName'  => array('type' => 'string', 'include_in_all' => TRUE)
        ),
    ),
    'msg'     => array('type' => 'string', 'include_in_all' => TRUE),
    'tstamp'  => array('type' => 'date', 'include_in_all' => FALSE),
    'location'=> array('type' => 'geo_point', 'include_in_all' => FALSE),
    '_boost'  => array('type' => 'float', 'include_in_all' => FALSE)
));

// Send mapping to type
$mapping->send();
function store_tweet($tweet) {
    global $elasticaType;

    // First parameter is the id of document.
    $tweetDocument = new \Elastica\Document($tweet['id'], $tweet);

    // Add tweet to type
    $elasticaType->addDocument($tweetDocument);

    // Refresh Index
    $elasticaType->getIndex()->refresh();
}

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
} else {
    echo "OK.\n";
}

/* Get the IP address for the target host. */
$address = gethostbyname('dwh-edge001.atl1.turn.com');
$service_port = 9293;

echo "Attempting to connect to '$address' on port '$service_port'...";

$result = socket_connect($socket, $address, $service_port);

if ($result === false) {
    echo "socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
} else {
    echo "OK.\n";
}

function send_to_logstash($line) {
  global $socket;
  $data = json_decode($line, true);
  $line = "$line\n";
//      print "sent:" . $data['user']['screen_name'] . ': ' . urldecode($data['text']) . "\n";
  socket_write($socket, $line, strlen($line));
}

/**
 * Example of using Phirehose to display a live filtered stream using geo locations
 */
class FilterTrackConsumer extends OauthPhirehose
{
  /**
   * Enqueue each status
   *
   * @param string $status
   */
  public function enqueueStatus($status)
  {
    /*
     * In this simple example, we will just display to STDOUT rather than enqueue.
     * NOTE: You should NOT be processing tweets at this point in a real application, instead they should be being
     *       enqueued and processed asyncronously from the collection process.
     */
    $data = json_decode($status, true);
    if (is_array($data) && isset($data['user']['screen_name'])) {
      print $data['user']['screen_name'] . ': ' . urldecode($data['text']) . "\n";
    }
    send_to_logstash($status);
    store_tweet($data);
  }
}

// The OAuth credentials you received when registering your app at Twitter
define("TWITTER_CONSUMER_KEY", "HPbz4ipF9Mj7cIgnNqWyN7TUo");
define("TWITTER_CONSUMER_SECRET", "CQhqz0W8rVNJHGFs4VSB41woDmTlMZaGyhlugSWq0VZOM5iXNr");

// The OAuth data for the twitter account
define("OAUTH_TOKEN", "118937716-jvXol4ELy2nlSdctNeZnnmR2ZCbFjx0unYqrmMGq");
define("OAUTH_SECRET", "bjyZ3iwV7wpIJuUar7kPHk7uHcsFQFd3axPFsqUJlg8aD");

// Start streaming
$sc = new FilterTrackConsumer(OAUTH_TOKEN, OAUTH_SECRET, Phirehose::METHOD_FILTER);
/*
$sc->setLocations(
  array(
    array(-122.75, 36.8, -121.75, 37.8), // San Francisco
    array(-74, 40, -73, 41)             // New York
  )
);
*/
/*
http://www.gps-coordinates.net/
stl
west -90.51
north 38.83
south 38.48
east -90.11
stclair
west -90.11
east -89.74
north 38.83
south 38.48
*/
$sc->setLocations(
  array(
    array(-90.51, 38.48, -90.11, 38.83), // St. Louis
    array(-90.11, 38.48, -89.74, 38.83) // St. Clair County
  )
);

$sc->consume();
?>
