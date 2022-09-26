<?php
namespace Webman;

class Install
{
    const WEBMAN_PLUGIN = true;

    /**
     * @var array
     */
    protected static $pathRelation = [
        'start.php' => 'start.php',
        'support/helpers.php' => 'support/helpers.php',
        'support/bootstrap.php' => 'support/bootstrap.php',
        'support/Plugin.php' => 'support/Plugin.php',
    ];

    /**
     * Install
     * @return void
     */
    public static function install()
    {
        $support_dir = __DIR__ . '/../../../../support';
        if (is_dir($support_dir)) {
            //remove_dir($support_dir);
        }
        static::installByRelation();
    }

    /**
     * Uninstall
     * @return void
     */
    public static function uninstall()
    {

    }

    /**
     * installByRelation
     * @return void
     */
    public static function installByRelation()
    {
        foreach (static::$pathRelation as $source => $dest) {
            if ($pos = strrpos($dest, '/')) {
                $parent_dir = base_path().'/'.substr($dest, 0, $pos);
                if (!is_dir($parent_dir)) {
                    mkdir($parent_dir, 0777, true);
                }
            }
            copy_dir(__DIR__ . "/$source", base_path()."/$dest", true);
            echo "Create $dest
";
        }
    }
    
}
