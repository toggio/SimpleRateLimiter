<?php
/**
 * SimpleRateLimiter v. 1.0 - 14/03/2024
 *
 * A PHP class for Redis-based token bucket rate limiting with delay support
 *
 * This class implements a token bucket rate-limiting mechanism using Redis.
 * It prevents too many concurrent requests by allowing a limited number of tokens 
 * (representing concurrent request capacity) to be consumed.
 *
 * Copyright (C) 2025 under Apache License, Version 2.0
 *
 * @author Luca Soltoggio
 * https://www.lucasoltoggio.it
 * https://github.com/toggio/SimpleRateLimiter
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *	 http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

class SimpleRateLimiter {
    private $bucketKey;
    private $maxTokens;
    private $ttl;
    private $useDelay;
    private $minDelay; // in microseconds
    private $maxDelay; // in microseconds
    private $redis;
    private $salt;

    /**
     * Constructor.
     *
     * @param int   $maxTokens       Maximum number of tokens (default: 10).
     * @param int   $ttl             Time-to-live for the bucket key in seconds (default: 60).
     * @param bool  $useDelay        Whether to enable the delay mechanism (default: true).
     * @param float $minDelaySeconds Minimum delay in seconds (default: 0).
     * @param float $maxDelaySeconds Maximum delay in seconds (default: 0.5).
     */
    public function __construct($maxTokens = 10, $ttl = 60, $useDelay = true, $minDelaySeconds = 0, $maxDelaySeconds = 0.5) {
        $this->maxTokens = $maxTokens;
        $this->ttl = $ttl;
        $this->useDelay = $useDelay;
        // Convert delay seconds to microseconds for usleep()
        $this->minDelay = (int)($minDelaySeconds * 1000000);
        $this->maxDelay = (int)($maxDelaySeconds * 1000000);
		
        // Derive a salt using the machine ID if available, otherwise fall back to system info.
        if (file_exists('/etc/machine-id')) {
            $this->salt = trim(file_get_contents('/etc/machine-id'));
        } else {
            $this->salt = php_uname() . phpversion();
        }

        // Generate a deterministic bucket key using the salt and the script filename.
        $this->bucketKey = "global_token_bucket_" . hash('sha256', $this->salt . $_SERVER['SCRIPT_FILENAME']);

        // Connect to Redis.
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);

        // Initialize the bucket key if it doesn't exist; otherwise, refresh its TTL.
        if (!$this->redis->exists($this->bucketKey)) {
            $this->redis->set($this->bucketKey, $this->maxTokens, $this->ttl);
        } else {
            $this->redis->expire($this->bucketKey, $this->ttl);
        }
    }

    /**
     * Attempts to acquire a token.
     *
     * Returns true if a token is successfully acquired, or false if the rate limit is exceeded.
     * If the delay mechanism is enabled, a delay proportional to the tokens consumed is applied.
     *
     * @return bool True if token acquired; false otherwise.
     */
    public function acquire() {
        // Atomically decrement the token count.
        $tokens = $this->redis->decr($this->bucketKey);

        if ($this->useDelay) {
            // Calculate the number of tokens consumed (considering the current token consumption).
            $consumed = $this->maxTokens - $tokens - 1;
            // Calculate the proportion relative to the maximum available tokens.
            $proportion = ($this->maxTokens > 1) ? $consumed / ($this->maxTokens - 1) : 1;
            // Clamp the proportion to a maximum of 1.
            $proportion = min($proportion, 1);
            // Calculate the delay in microseconds.
            $delay = $this->minDelay + ($this->maxDelay - $this->minDelay) * $proportion;
            usleep((int)$delay);
        }

        if ($tokens < 0) {
            // If tokens are negative, restore the token and indicate failure.
            $this->redis->incr($this->bucketKey);
            return false;
        }

        return true;
    }

    /**
     * Releases a token by incrementing the bucket.
     */
    public function release() {
        $this->redis->incr($this->bucketKey);
    }
	
    /**
     * Debug function that returns the current number of free tokens available.
     *
     * @return int The current free token count.
     */
    public function debugFreeTokens() {
        return (int)$this->redis->get($this->bucketKey);
    }
}
?>
