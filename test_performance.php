<?php

// Backend Performance Test
class BackendPerformanceTest {
    private $baseUrl = 'http://127.0.0.1:8000/api';
    private $results = [];

    public function testEndpoint($endpoint, $name) {
        $startTime = microtime(true);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000); // Convert to milliseconds
        
        $result = [
            'name' => $name,
            'endpoint' => $endpoint,
            'response_time' => $responseTime,
            'http_code' => $httpCode,
            'success' => $httpCode >= 200 && $httpCode < 300 && empty($error),
            'error' => $error
        ];
        
        $this->results[] = $result;
        return $result;
    }

    public function getRating($time) {
        if ($time < 100) return ['label' => 'Excellent', 'icon' => '🚀'];
        if ($time < 300) return ['label' => 'Good', 'icon' => '⚡'];
        if ($time < 500) return ['label' => 'Average', 'icon' => '⚠️'];
        if ($time < 1000) return ['label' => 'Slow', 'icon' => '🐌'];
        return ['label' => 'Very Slow', 'icon' => '💀'];
    }

    public function runTests() {
        echo "🚀 BACKEND PERFORMANCE TEST\n";
        echo str_repeat('=', 50) . "\n\n";

        $endpoints = [
            ['/products', 'Products List'],
            ['/categories', 'Categories'],
            ['/brands', 'Brands'],
            ['/news', 'News'],
            ['/comments', 'Comments'],
            ['/vouchers/available', 'Available Vouchers'],
            ['/test', 'Health Check']
        ];

        echo "Testing API Endpoints...\n\n";

        foreach ($endpoints as [$endpoint, $name]) {
            $result = $this->testEndpoint($endpoint, $name);
            $rating = $this->getRating($result['response_time']);
            $status = $result['success'] ? '✅' : '❌';
            
            echo sprintf(
                "%s %-20s: %4dms %s %s\n",
                $status,
                $name,
                $result['response_time'],
                $rating['icon'],
                $rating['label']
            );
            
            if (!$result['success']) {
                echo "   Error: " . ($result['error'] ?: "HTTP {$result['http_code']}") . "\n";
            }
        }

        $this->generateReport();
    }

    public function generateReport() {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "📊 PERFORMANCE REPORT\n";
        echo str_repeat('=', 50) . "\n";

        $successfulTests = array_filter($this->results, fn($r) => $r['success']);
        $responseTimes = array_column($successfulTests, 'response_time');

        if (empty($responseTimes)) {
            echo "❌ No successful tests to analyze\n";
            return;
        }

        $avgTime = round(array_sum($responseTimes) / count($responseTimes));
        $minTime = min($responseTimes);
        $maxTime = max($responseTimes);
        $successRate = count($successfulTests) . '/' . count($this->results);

        echo "🔗 API Performance Summary:\n";
        echo "   Average Response Time: {$avgTime}ms " . $this->getRating($avgTime)['icon'] . "\n";
        echo "   Fastest Response: {$minTime}ms\n";
        echo "   Slowest Response: {$maxTime}ms\n";
        echo "   Success Rate: {$successRate}\n\n";

        // Recommendations
        echo "💡 Recommendations:\n";
        if ($avgTime > 500) {
            echo "   ⚠️ API response time is slow. Consider:\n";
            echo "      - Database query optimization\n";
            echo "      - Response caching (Redis)\n";
            echo "      - Database indexing\n";
            echo "      - Query result pagination\n";
        } elseif ($avgTime > 300) {
            echo "   ⚡ API performance is good but can be improved:\n";
            echo "      - Implement API caching\n";
            echo "      - Optimize database queries\n";
        } else {
            echo "   🎉 Excellent API performance!\n";
        }

        if (count($successfulTests) < count($this->results)) {
            echo "   ❌ Some endpoints failed. Check:\n";
            echo "      - Server configuration\n";
            echo "      - Database connection\n";
            echo "      - Route definitions\n";
        }

        echo "\n" . str_repeat('=', 50) . "\n";
    }
}

// Run the test
$tester = new BackendPerformanceTest();
$tester->runTests();