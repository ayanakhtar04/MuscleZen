<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../php/utils/logger.php';

class LoadTester {
    private $base_url;
    private $concurrent;
    private $total_requests;
    private $results = [];
    
    public function __construct($base_url, $concurrent = 10, $total_requests = 100) {
        $this->base_url = $base_url;
        $this->concurrent = $concurrent;
        $this->total_requests = $total_requests;
    }
    
    public function runTest($endpoint, $method = 'GET', $data = null) {
        echo "Starting load test for $endpoint\n";
        echo "Concurrent requests: $this->concurrent\n";
        echo "Total requests: $this->total_requests\n\n";
        
        $start_time = microtime(true);
        $completed = 0;
        $batch = 0;
        
        while ($completed < $this->total_requests) {
            $batch++;
            $processes = [];
            $batch_size = min($this->concurrent, $this->total_requests - $completed);
            
            echo "Running batch $batch ($batch_size requests)...\n";
            
            for ($i = 0; $i < $batch_size; $i++) {
                $processes[] = $this->createCurlProcess($endpoint, $method, $data);
            }
            
            $running = null;
            do {
                curl_multi_exec($mh = curl_multi_init(), $running);
            } while ($running > 0);
            
            foreach ($processes as $process) {
                $this->processResult(curl_multi_getcontent($process));
                curl_multi_remove_handle($mh, $process);
                curl_close($process);
            }
            
            curl_multi_close($mh);
            $completed += $batch_size;
            
            echo "Completed $completed requests\n";
        }
        
        $total_time = microtime(true) - $start_time;
        $this->generateReport($total_time);
    }
    
    private function createCurlProcess($endpoint, $method, $data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }
        
        return $ch;
    }
    
    private function processResult($content) {
        $response = json_decode($content, true);
        $this->results[] = [
            'success' => isset($response['status']) && $response['status'] === 'success',
            'response_time' => curl_getinfo($ch)['total_time'] ?? 0,
            'response_size' => strlen($content)
        ];
    }
    
    private function generateReport($total_time) {
        $successful = array_filter($this->results, fn($r) => $r['success']);
        $response_times = array_column($this->results, 'response_time');
        
        $report = "\nLoad Test Report\n";
        $report .= "================\n\n";
        $report .= "Total Requests: {$this->total_requests}\n";
        $report .= "Concurrent Requests: {$this->concurrent}\n";
        $report .= "Total Time: " . round($total_time, 2) . " seconds\n";
        $report .= "Requests/Second: " . round($this->total_requests / $total_time, 2) . "\n";
        $report .= "Success Rate: " . round(count($successful) / count($this->results) * 100, 2) . "%\n";
        $report .= "Average Response Time: " . round(array_sum($response_times) / count($response_times), 3) . " seconds\n";
        $report .= "Max Response Time: " . round(max($response_times), 3) . " seconds\n";
        $report .= "Min Response Time: " . round(min($response_times), 3) . " seconds\n";
        
        echo $report;
        Logger::info($report);
    }
}

// Example usage
$tester = new LoadTester('http://localhost');

// Test login endpoint
$tester->runTest('/php/login.php', 'POST', [
    'email' => 'test@example.com',
    'password' => 'password123'
]);

// Test get profile endpoint
$tester->runTest('/php/get
