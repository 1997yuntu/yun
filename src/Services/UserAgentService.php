<?php
/**
 * User-Agent检测服务类
 * 使用crawler-user-agents.json和ua-parser.min.js的数据结构
 */

class UserAgentService {
    private $crawlerPatterns = null;
    
    public function __construct() {
        $this->loadCrawlerPatterns();
    }
    
    /**
     * 加载爬虫模式数据
     */
    private function loadCrawlerPatterns() {
        $jsonFile = __DIR__ . '/../../public/assets/js/crawler-user-agents.json';
        if (file_exists($jsonFile)) {
            $this->crawlerPatterns = json_decode(file_get_contents($jsonFile), true);
        }
    }
    
    /**
     * 检测是否为爬虫（使用JSON文件数据）
     */
    public function isCrawler($userAgent) {
        if (!$this->crawlerPatterns) {
            return false;
        }
        
        $userAgent = strtolower($userAgent);
        
        foreach ($this->crawlerPatterns as $crawler) {
            if (isset($crawler['pattern'])) {
                $pattern = strtolower($crawler['pattern']);
                // 简单的字符串匹配（可以改进为正则表达式）
                if (strpos($userAgent, str_replace('\\/', '/', $pattern)) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * 增强的机器人类型检测
     */
    public function detectBotType($userAgent) {
        $userAgent = strtolower($userAgent);
        
        // 首先检查是否在已知爬虫列表中
        if ($this->crawlerPatterns) {
            foreach ($this->crawlerPatterns as $crawler) {
                if (isset($crawler['pattern'])) {
                    $pattern = strtolower(str_replace('\\/', '/', $crawler['pattern']));
                    if (strpos($userAgent, $pattern) !== false) {
                        return $this->categorizeCrawler($pattern, $userAgent);
                    }
                }
            }
        }
        
        // 如果不在已知列表中，使用自定义规则
        return $this->detectCustomBotType($userAgent);
    }
    
    /**
     * 根据爬虫模式分类
     */
    private function categorizeCrawler($pattern, $userAgent) {
        // AI爬虫模式
        $aiPatterns = [
            'gptbot', 'chatgpt', 'claude', 'bard', 'gemini', 
            'openai', 'anthropic', 'cohere', 'perplexity'
        ];
        
        // 搜索引擎爬虫模式
        $searchPatterns = [
            'googlebot', 'bingbot', 'slurp', 'baiduspider', 
            'yandexbot', 'duckduckbot', 'yahoo', 'bing', 'google'
        ];
        
        // 社交媒体爬虫模式
        $socialPatterns = [
            'facebookexternalhit', 'twitterbot', 'linkedinbot', 
            'whatsapp', 'telegram', 'facebook', 'twitter', 'linkedin'
        ];
        
        foreach ($aiPatterns as $aiPattern) {
            if (strpos($pattern, $aiPattern) !== false || strpos($userAgent, $aiPattern) !== false) {
                return $this->getAIBotInfo($pattern, $userAgent);
            }
        }
        
        foreach ($searchPatterns as $searchPattern) {
            if (strpos($pattern, $searchPattern) !== false) {
                return $this->getSearchBotInfo($pattern);
            }
        }
        
        foreach ($socialPatterns as $socialPattern) {
            if (strpos($pattern, $socialPattern) !== false) {
                return $this->getSocialBotInfo($pattern);
            }
        }
        
        return null;
    }
    
    /**
     * 获取AI机器人信息
     */
    private function getAIBotInfo($pattern, $userAgent) {
        if (strpos($pattern, 'gptbot') !== false || strpos($userAgent, 'gptbot') !== false) {
            return ['type' => 'AI爬虫', 'name' => 'OpenAI GPTBot', 'category' => 'ai'];
        }
        if (strpos($pattern, 'chatgpt') !== false || strpos($userAgent, 'chatgpt') !== false) {
            return ['type' => 'AI爬虫', 'name' => 'ChatGPT', 'category' => 'ai'];
        }
        if (strpos($pattern, 'claude') !== false || strpos($userAgent, 'claude') !== false) {
            return ['type' => 'AI爬虫', 'name' => 'Anthropic Claude', 'category' => 'ai'];
        }
        if (strpos($pattern, 'bard') !== false || strpos($userAgent, 'bard') !== false) {
            return ['type' => 'AI爬虫', 'name' => 'Google Bard', 'category' => 'ai'];
        }
        if (strpos($pattern, 'gemini') !== false || strpos($userAgent, 'gemini') !== false) {
            return ['type' => 'AI爬虫', 'name' => 'Google Gemini', 'category' => 'ai'];
        }
        
        return ['type' => 'AI爬虫', 'name' => 'AI Bot', 'category' => 'ai'];
    }
    
    /**
     * 获取搜索引擎机器人信息
     */
    private function getSearchBotInfo($pattern) {
        if (strpos($pattern, 'googlebot') !== false || strpos($pattern, 'google') !== false) {
            return ['type' => '搜索引擎', 'name' => 'Google Bot', 'category' => 'search'];
        }
        if (strpos($pattern, 'bingbot') !== false || strpos($pattern, 'bing') !== false) {
            return ['type' => '搜索引擎', 'name' => 'Bing Bot', 'category' => 'search'];
        }
        if (strpos($pattern, 'slurp') !== false || strpos($pattern, 'yahoo') !== false) {
            return ['type' => '搜索引擎', 'name' => 'Yahoo Bot', 'category' => 'search'];
        }
        if (strpos($pattern, 'baiduspider') !== false) {
            return ['type' => '搜索引擎', 'name' => 'Baidu Spider', 'category' => 'search'];
        }
        if (strpos($pattern, 'yandexbot') !== false) {
            return ['type' => '搜索引擎', 'name' => 'Yandex Bot', 'category' => 'search'];
        }
        
        return ['type' => '搜索引擎', 'name' => 'Search Bot', 'category' => 'search'];
    }
    
    /**
     * 获取社交媒体机器人信息
     */
    private function getSocialBotInfo($pattern) {
        if (strpos($pattern, 'facebook') !== false) {
            return ['type' => '社交媒体', 'name' => 'Facebook Bot', 'category' => 'social'];
        }
        if (strpos($pattern, 'twitter') !== false) {
            return ['type' => '社交媒体', 'name' => 'Twitter Bot', 'category' => 'social'];
        }
        if (strpos($pattern, 'linkedin') !== false) {
            return ['type' => '社交媒体', 'name' => 'LinkedIn Bot', 'category' => 'social'];
        }
        if (strpos($pattern, 'whatsapp') !== false) {
            return ['type' => '社交媒体', 'name' => 'WhatsApp Bot', 'category' => 'social'];
        }
        if (strpos($pattern, 'telegram') !== false) {
            return ['type' => '社交媒体', 'name' => 'Telegram Bot', 'category' => 'social'];
        }
        
        return ['type' => '社交媒体', 'name' => 'Social Bot', 'category' => 'social'];
    }
    
    /**
     * 自定义机器人类型检测（备用方案）
     */
    private function detectCustomBotType($userAgent) {
        // AI爬虫检测
        $aiPatterns = [
            'gptbot' => 'OpenAI GPTBot',
            'chatgpt' => 'ChatGPT',
            'claude' => 'Anthropic Claude',
            'bard' => 'Google Bard',
            'gemini' => 'Google Gemini',
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'cohere' => 'Cohere AI',
            'perplexity' => 'Perplexity AI'
        ];
        
        // 搜索引擎爬虫
        $searchEnginePatterns = [
            'googlebot' => 'Google Bot',
            'bingbot' => 'Bing Bot',
            'slurp' => 'Yahoo Bot',
            'baiduspider' => 'Baidu Spider',
            'yandexbot' => 'Yandex Bot',
            'duckduckbot' => 'DuckDuckGo Bot'
        ];
        
        // 社交媒体爬虫
        $socialPatterns = [
            'facebookexternalhit' => 'Facebook Bot',
            'twitterbot' => 'Twitter Bot',
            'linkedinbot' => 'LinkedIn Bot',
            'whatsapp' => 'WhatsApp Bot',
            'telegrambot' => 'Telegram Bot'
        ];
        
        // 检查AI爬虫
        foreach ($aiPatterns as $pattern => $name) {
            if (strpos($userAgent, $pattern) !== false) {
                return ['type' => 'AI爬虫', 'name' => $name, 'category' => 'ai'];
            }
        }
        
        // 检查搜索引擎爬虫
        foreach ($searchEnginePatterns as $pattern => $name) {
            if (strpos($userAgent, $pattern) !== false) {
                return ['type' => '搜索引擎', 'name' => $name, 'category' => 'search'];
            }
        }
        
        // 检查社交媒体爬虫
        foreach ($socialPatterns as $pattern => $name) {
            if (strpos($userAgent, $pattern) !== false) {
                return ['type' => '社交媒体', 'name' => $name, 'category' => 'social'];
            }
        }
        
        return null;
    }
    
    /**
     * 解析User-Agent获取浏览器、操作系统信息
     * 这里可以集成ua-parser.min.js的逻辑或使用PHP版本
     */
    public function parseUserAgent($userAgent) {
        // 检查输入是否为空或null
        if (empty($userAgent) || $userAgent === null) {
            return [
                'browser' => '未知浏览器',
                'browser_version' => '',
                'os' => '未知系统',
                'os_version' => '',
                'device_type' => '未知设备'
            ];
        }
        
        // 浏览器检测（改进版）
        $browser = '未知浏览器';
        $browserVersion = '';
        
        // 主流浏览器匹配规则（按优先级排序）
        $browsers = [
            '/Edg\/([0-9\.]+)/' => 'Edge',
            '/Edge\/([0-9\.]+)/' => 'Edge',
            '/Chrome\/([0-9\.]+)/' => 'Chrome',
            '/Firefox\/([0-9\.]+)/' => 'Firefox',
            '/Version\/([0-9\.]+).*Safari/' => 'Safari',
            '/Safari\/([0-9\.]+)/' => 'Safari',
            '/OPR\/([0-9\.]+)/' => 'Opera',
            '/Opera\/([0-9\.]+)/' => 'Opera',
            '/MSIE ([0-9\.]+)/' => 'IE',
            '/Trident.*rv:([0-9\.]+)/' => 'IE'
        ];
        
        foreach ($browsers as $pattern => $name) {
            if (preg_match($pattern, $userAgent, $matches)) {
                $browser = $name;
                $browserVersion = $matches[1] ?? '';
                break;
            }
        }
        
        // 操作系统检测
        $os = '未知系统';
        $osVersion = '';
        
        if (preg_match('/Windows NT ([0-9\.]+)/', $userAgent, $matches)) {
            $os = 'Windows';
            $winVersions = [
                '10.0' => '10', '6.3' => '8.1', '6.2' => '8',
                '6.1' => '7', '6.0' => 'Vista', '5.1' => 'XP'
            ];
            $osVersion = $winVersions[$matches[1]] ?? $matches[1];
        } elseif (preg_match('/Mac OS X ([0-9_\.]+)/', $userAgent, $matches)) {
            $os = 'macOS';
            $osVersion = str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/Android ([0-9\.]+)/', $userAgent, $matches)) {
            $os = 'Android';
            $osVersion = $matches[1];
        } elseif (preg_match('/iPhone OS ([0-9_\.]+)/', $userAgent, $matches)) {
            $os = 'iOS';
            $osVersion = str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/iPad.*OS ([0-9_\.]+)/', $userAgent, $matches)) {
            $os = 'iPadOS';
            $osVersion = str_replace('_', '.', $matches[1]);
        } elseif (strpos($userAgent, 'Linux') !== false) {
            $os = 'Linux';
        }
        
        // 设备类型检测
        $deviceType = 'Desktop';
        if (preg_match('/(iPhone|iPod)/', $userAgent)) {
            $deviceType = 'Mobile';
        } elseif (preg_match('/(iPad|Android.*Mobile|BlackBerry|Windows Phone)/', $userAgent)) {
            $deviceType = preg_match('/iPad/', $userAgent) ? 'Tablet' : 'Mobile';
        } elseif (preg_match('/Android/', $userAgent) && !preg_match('/Mobile/', $userAgent)) {
            $deviceType = 'Tablet';
        }
        
        return [
            'browser' => $browser,
            'browser_version' => $browserVersion,
            'os' => $os,
            'os_version' => $osVersion,
            'device_type' => $deviceType,
            'full_name' => $browser . ($browserVersion ? ' ' . $browserVersion : '') . ' / ' . $os . ($osVersion ? ' ' . $osVersion : '')
        ];
    }
}
?>
