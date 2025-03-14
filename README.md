# SimpleRateLimiter
A PHP class for Redis-based token bucket rate limiting with delay support

## Overview

The **SimpleRateLimiter** class implements a token bucket rate-limiting mechanism using Redis. It prevents too many concurrent requests by allowing a limited number of tokens (representing concurrent request capacity) to be consumed.

The bucket key is generated internally using a salt derived from the machine's unique ID (or system info) and the script filename, ensuring that the key is unique per script and cannot be overridden by the user.

## Features

- **Rate Limiting:** Limits the number of concurrent requests using a token bucket algorithm.
- **Redis-Based:** Utilizes Redis for fast, atomic operations.
- **Deterministic Bucket Key:** Automatically generates a unique bucket key based on the server's machine ID and script filename.
- **Delay Mechanism:** Optionally applies a delay proportional to the number of tokens already consumed.
- **Automatic TTL Management:** Refreshes the TTL for the bucket key on each request.

## Requirements

- **PHP** (version 5.6 or higher is recommended)
- **Redis Server**
- **PHP Redis Extension**

## Installation

1. **Install Redis:**

   Follow your platform-specific instructions to install and run Redis. For example, on Debian/Ubuntu:
   ```bash
   sudo apt-get update
   sudo apt-get install redis-server
   ```
2. **Install the PHP Redis Extension:**

   On Debian/Ubuntu:
   ```bash
   sudo apt-get install php-redis
   ```
   Then restart your web server (e.g., Apache or PHP-FPM).

3. **Download or Clone the Repository:**

   Clone this repository to your project directory:
   ```bash
   git clone https://github.com/yourusername/SimpleRateLimiter.git
   ```
   Or simply download the `SimpleRateLimiter.php` file and include it in your project.

## Usage

Include the class in your script and create an instance using the default parameters or override them as needed. For example:

```php
<?php
require_once 'SimpleRateLimiter.php';

// Create an instance with default parameters:
//   - maxTokens: 10
//   - ttl: 60 seconds
//   - useDelay: true
//   - minDelaySeconds: 0 seconds
//   - maxDelaySeconds: 0.5 seconds
$limiter = new SimpleRateLimiter();

if (!$limiter->acquire()) {
    header("HTTP/1.1 429 Too Many Requests");
    echo "Too many requests, please try again later.";
    exit();
}

try {
    // Critical section: Place your code here.
    echo "Executing critical operations...\n";
    // ... your operations here ...
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} finally {
    // Ensure the token is always released.
    $limiter->release();
}
?>
```

## Reference

### `__construct($maxTokens = 10, $ttl = 60, $useDelay = true, $minDelaySeconds = 0, $maxDelaySeconds = 0.5)`
Initializes the rate limiter.

**Parameters:**
- `$maxTokens` (int): Maximum number of tokens allowed (default: 10).
- `$ttl` (int): Time-to-live for the bucket key in seconds (default: 60).
- `$useDelay` (bool): Whether to enable the delay mechanism (default: true).
- `$minDelaySeconds` (float): Minimum delay in seconds (default: 0).
- `$maxDelaySeconds` (float): Maximum delay in seconds (default: 0.5).

### `acquire()`
Attempts to acquire a token.

**Returns:**
- `true` if a token is successfully acquired.
- `false` if the rate limit is exceeded (i.e., no token is available).

If the delay mechanism is enabled, a delay proportional to the tokens consumed is applied before determining if a token is available.

### `release()`
Releases a token back to the bucket by incrementing the token count.

## How it Works

1. **Bucket Key Generation:**  
The bucket key is generated using a salt derived from `/etc/machine-id` (if available) or system information (via `php_uname()` and `phpversion()`), combined with the script filename. This ensures that each script uses a unique bucket key.

2. **Token Management:**  
The token bucket is stored in Redis. If the bucket key does not exist, it is initialized with the maximum number of tokens and a specified TTL. For each request, a token is atomically consumed using Redis's `DECR` command.

3. **Delay Mechanism:**  
When enabled, the delay applied is proportional to the number of tokens consumed. This helps to throttle request processing when the bucket is nearing exhaustion.

4. **TTL Refresh:**  
The TTL for the bucket key is refreshed on each request, ensuring that the bucket persists as long as requests are made within the TTL period.

## Contributing

Contributions, issues, and feature requests are welcome! Feel free to fork the repository, make changes, and submit pull requests. Please open issues for any bugs or enhancements you have in mind.

## Help us

If you find this project useful and would like to support its development, consider making a donation. Any contribution is greatly appreciated!

**Bitcoin (BTC) Addresses:**
- **1LToggio**f3rNUTCemJZSsxd1qubTYoSde6  
- **3LToggio**7Xx8qMsjCFfiarV4U2ZR9iU9ob

## License
**SimpleRateLimiter** library is licensed under the Apache License, Version 2.0. You are free to use, modify, and distribute the library in compliance with the license.

Copyright (C) 2024 Luca Soltoggio - https://www.lucasoltoggio.it/
