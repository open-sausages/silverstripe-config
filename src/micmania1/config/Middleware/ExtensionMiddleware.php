<?php

namespace micmania1\config\Middleware;

use Generator;
use InvalidArgumentException;
use micmania1\config\MergeStrategy\Priority;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Object;

class ExtensionMiddleware implements Middleware
{
    /**
     * Get config for a class
     *
     * @param string $class
     * @param callable $next
     * @return string
     */
    public function getClassConfig($class, $next)
    {
        // Apply extensions to this class
        $config = $next();

        $priority = new Priority();
        foreach ($this->getExtraConfig($class, $config) as $extra) {
            $config = $priority->mergeArray($extra, $config);
        }
        return $config;
    }

    /**
     * Applied config to a class from its extensions
     *
     * @param string $class
     * @param array $classConfig
     * @return Generator
     */
    protected function getExtraConfig($class, $classConfig)
    {
        if (empty($classConfig['extensions'])) {
            return;
        }

        $extensions = $classConfig['extensions'];
        foreach ($extensions as $extension) {
            list($extensionClass, $extensionArgs) = Object::parse_class_spec($extension);
            if (!class_exists($extensionClass)) {
                throw new InvalidArgumentException("$class references nonexistent $extensionClass in 'extensions'");
            }

            // Init extension
            call_user_func(array($extensionClass, 'add_to_class'), $class, $extensionClass, $extensionArgs);

            // Check class hierarchy from root up
            foreach (ClassInfo::ancestry($extensionClass) as $extensionClassParent) {
                // Merge config from extension
                $extensionConfig = Config::inst()->get($extensionClassParent, null, false);
                if ($extensionConfig) {
                    yield $extensionConfig;
                }
                if (ClassInfo::has_method_from($extensionClassParent, 'get_extra_config', $extensionClassParent)) {
                    $extensionConfig = call_user_func(
                        [ $extensionClassParent, 'get_extra_config' ],
                        $class,
                        $extensionClass,
                        $extensionArgs
                    );
                    if ($extensionConfig) {
                        yield $extensionConfig;
                    }
                }
            }
        }
    }
}
