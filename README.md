# Featured Flags
[![Build Status](https://travis-ci.org/minube/feature-flag.png)](https://travis-ci.org/minube/feature-flag) [![Code Coverage](https://scrutinizer-ci.com/g/minube/featured-flags/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/minube/featured-flags/?branch=master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/minube/featured-flags/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/minube/featured-flags/?branch=master) [![Build Status](https://scrutinizer-ci.com/g/minube/featured-flags/badges/build.png?b=master)](https://scrutinizer-ci.com/g/minube/featured-flags/build-status/master)

Featured flag to activate functionality without release new version


## How to use it


###### Database Example (MySQL):
    CREATE TABLE IF NOT EXISTS `featured_flags` (
      `id` int(10) NOT NULL,
      `name` varchar(100) NOT NULL,
      `status` int(1) DEFAULT 0,
      `start_date` timestamp DEFAULT NULL,
      `end_date` timestamp DEFAULT NULL,
      `params` TEXT DEFAULT NULL,
      `return_params` TEXT DEFAULT NULL,
       PRIMARY KEY (`id`)
    );

###### Example DB Content
```php

/**
* (id,name,status,start_date,end_date,params,return_params)
* (1,flag1,1,null,null,null,{"data1":"data1_id1","data2":"data2_id1"})
* (2,flag1,1,null,null,{"type":"typeA"},{"data1":"value1","data2":"value2"})
* (3,flag1,1,null,null,{"type":"typeB"},{"data1":"with out color"})
* (4,flag1,1,null,null,{"type":"typeB","color":"Red"},{"data1":"with color"})
* (5,flagDisabled,0,null,null,null,{"data1":"name flagDisabled"})
* (6,flagJanuary,1,2016-01-01 00:00:00,2016-01-31 23:59:59,null,null)
* (7,flagSinceJanuary,1,2016-01-01 00:00:00,null,null,null)
* (8,flagUntilFebruary,1,null,2016-01-31 23:59:59,null,null,null)
*/
```

######Initialize
```php
$pdo = new PDO();
$redis = new Redis();
$featuredFlags = \FeaturedFlags\FeaturedFlagsImpl::getInstance($pdo, $redis);
$flagName = 'flag1';
$filterValuesTypeA = array('type' => 'typeA');
$filterValuesTypeBAndColorRed = array('type' => 'typeB', 'color' => 'red');
```

###### Flag enabled without filter parameters
```php
if($featuredFlags->isEnabled($flagName))
{
    echo("flag1 is enabled (id:1)");
}
// flag1 is enabled (id:1)
```

###### Flag disabled without filter parameters
```php
if($featuredFlags->isEnabled('flagDisabled'))
{
    echo("no here");
} else {
    echo("flagDisabled is disabled (id:5)");
}
// flagDisabled is disabled (id:5)
```

###### Flag enabled with one filter parameter
```php
if($featuredFlags->isEnabled($flagName, $filterValuesTypeA))
{
    echo('flag1 with params = {"type":"typeA"} is enabled (id:2)');
}
// flag1 with params = {"type":"typeA"} is enabled (id:2)
```

###### Flag enabled with multiple filter parameters
```php
if($featuredFlags->isEnabled($flagName, $filterValuesTypeBAndColorRed))
{
    echo('flag1 with params = {"type":"typeB","color":"Red"} is enabled (id:4)');
}
// flag1 with params = {"type":"typeB","color":"Red"} is enabled (id:4)
```

###### Flag enabled by Date
```php
// today = '2016-01-15 00:00:00'
if($featuredFlags->isEnabled('flagJanuary', $filterValuesTypeBAndColorRed))
{
    echo('flagJanuary is enabled only in January 2016');
}
// flagJanuary is enabled only in January 2016
```

###### Flag enabled by StartDate
```php
// today = '2016-05-15 00:00:00'
if($featuredFlags->isEnabled('flagSinceJanuary', $filterValuesTypeBAndColorRed))
{
    echo('flagSinceJanuary is enabled after January 2016');
}
// flagSinceJanuary is enabled after January 2016
```

###### Flag enabled by EndDate
```php
// today = '2016-02-15 00:00:00'
if($featuredFlags->isEnabled('flagUntilFebruary', $filterValuesTypeBAndColorRed))
{
    echo('no here');
} else {
    echo('flagUntilFebruary is disabled after February 2016');
}
// flagUntilFebruary is disabled after February 2016
```

###### Get Flag values with without filter parameters
```php
$flagValues = $featuredFlags->getEnabledValues($flagName);
echo('This are the flag1(id:1) values: ');
print_r($flagValues);
/**
* This are the flag1(id:1) values: 
* array(
*   'data1' => data1_id1,
*   'data2' => data2_id1
* );
*/
```

###### Get Flag values from disabled flag
```php
$flagValuesDisabled = $featuredFlags->getEnabledValues('flagDisabled');
echo('The flagDisabled values are disabled: ');
print_r($flagValuesDisabled);
/**
* The flagDisabled values are disabled: 
* array();
*/
```

###### Get Flag values from disabled flag with one filter parameter
```php
$flagValuesTypeA = $featuredFlags->getEnabledValues($flagName, $filterValuesTypeA);
echo('This are the flag1(id:2) with params = {"type":"typeA"} values: ');
print_r($flagValuesTypeA);
/**
* This are the flag1(id:2) with params = {"type":"typeA"} values: 
* array(
*   'data1' => value1,
*   'data2' => value2
* );
*/
```

###### Get Flag values from disabled flag multiple filter parameters
```php
$flagValuesTypeBRed = $featuredFlags->getEnabledValues($flagName, $filterValuesTypeBAndColorRed);
echo('This are the flag1 with params = {"type":"typeB","color":"Red"} values: ');
print_r($flagValuesTypeBRed);
/**
* 'This are the flag1 with params = {"type":"typeB","color":"Red"} values: 
* array(
*   'data1' => with color
* );
*/
