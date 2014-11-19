<?php 
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
                    'language' => 'English'
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
    'id'      => array('type' => 'integer', 'include_in_all' => FALSE),
    'user'    => array(
        'type' => 'object',
        'properties' => array(
            'name'      => array('type' => 'string', 'include_in_all' => TRUE),
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
?>
