<?php
/**
 * Performance Optimization Utility Class
 * Handles caching and performance improvements
 */
class Performance {
    private static $cacheDir = __DIR__ . '/../cache/';
    
    /**
     * Initialize cache directory
     */
    public static function init() {
        if (!file_exists(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    /**
     * Get cached data
     */
    public static function getCache($key) {
        self::init();
        $cacheFile = self::$cacheDir . md5($key) . '.cache';
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $data = unserialize(file_get_contents($cacheFile));
        
        // Check if expired
        if ($data['expires'] < time()) {
            unlink($cacheFile);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * Set cache data
     */
    public static function setCache($key, $value, $ttl = 3600) {
        self::init();
        $cacheFile = self::$cacheDir . md5($key) . '.cache';
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        file_put_contents($cacheFile, serialize($data));
    }
    
    /**
     * Clear specific cache
     */
    public static function clearCache($key) {
        self::init();
        $cacheFile = self::$cacheDir . md5($key) . '.cache';
        
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }
    
    /**
     * Clear all cache
     */
    public static function clearAllCache() {
        self::init();
        $files = glob(self::$cacheDir . '*.cache');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    /**
     * Minify HTML output
     */
    public static function minifyHTML($html) {
        $search = [
            '/\>[^\S ]+/s',     // Strip whitespace after tags
            '/[^\S ]+\</s',     // Strip whitespace before tags
            '/(\s)+/s',         // Shorten multiple whitespace sequences
            '/<!--(.|\s)*?-->/' // Remove HTML comments
        ];
        
        $replace = [
            '>',
            '<',
            '\\1',
            ''
        ];
        
        return preg_replace($search, $replace, $html);
    }
    
    /**
     * Compress output
     */
    public static function enableCompression() {
        if (!ob_start('ob_gzhandler')) {
            ob_start();
        }
    }
    
    /**
     * Get optimized database connection settings
     */
    public static function getOptimizedDBSettings() {
        return [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
    }
}
