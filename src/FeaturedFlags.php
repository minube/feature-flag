<?php

namespace FeaturedFlags;

use PDO;
use Redis;

interface FeaturedFlags
{
    public function __construct(PDO $pdo, Redis $redis = null, $date = null);

    public static function getInstance(PDO $pdo, Redis $redis = null);

    public function isEnabled($flagName, $filterParams = null);

    public function getEnabledValues($flagName, $filterParams = null);

    public function setRedis(Redis $redis);
}
