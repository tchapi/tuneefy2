<?php

namespace tuneefy\Command;

use Symfony\Component\Yaml\Yaml;
use tuneefy\DB\DatabaseHandler;

class ExpiredIntentsCleanerCommand
{
    public const PATHS = [
        'parameters' => '/../../../app/config/parameters.yml',
    ];

    public static function run()
    {
        $params = Yaml::parse(file_get_contents(dirname(__FILE__).self::PATHS['parameters']));
        try {
            $db = new DatabaseHandler($params);
            $result = $db->cleanExpiredIntents();
            $result = $db->cleanExpiredAccessTokens();
        } catch (\Exception $e) {
            throw new \Exception('Problem with database instantiation : '.$e->getMessage());
        }
    }
}
