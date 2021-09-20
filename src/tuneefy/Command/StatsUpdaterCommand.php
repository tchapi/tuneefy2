<?php

namespace tuneefy\Command;

use Symfony\Component\Yaml\Yaml;
use tuneefy\DB\DatabaseHandler;

class StatsUpdaterCommand
{
    public const PATHS = [
        'parameters' => '/../../../app/config/parameters.yml',
    ];

    public static function run()
    {
        $params = Yaml::parse(file_get_contents(dirname(__FILE__).self::PATHS['parameters']));
        try {
            $db = new DatabaseHandler($params);
            $result = $db->updateMaterializedViews();
        } catch (\Exception $e) {
            throw new \Exception('Problem with database instantiation : '.$e->getMessage());
        }
    }
}
