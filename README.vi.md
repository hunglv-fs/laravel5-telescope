# Telescope cho Laravel 5.0

Bản port ngược của [laravel/telescope](https://github.com/laravel/telescope) về **Laravel 5.0 / PHP 5.4+**.

Ghi lại request, query, queue job, artisan command, exception, log, cache và mail — mỗi thứ được gom
vào đúng *batch* đã sinh ra nó — kèm trang **Insights** chỉ thẳng ra query N+1 và những câu SQL đang
đốt nhiều thời gian nhất. Không npm, không build, dashboard render bằng Blade.

> 🇬🇧 [English version](README.md)

## Vì sao phải port chứ không cài Telescope gốc

Telescope yêu cầu Laravel ≥ 5.7.7 và PHP ≥ 7.1. Rào cản không nằm ở ràng buộc version mà ở API:

| Telescope gốc dùng | Laravel 5.0 có gì | Bản port này làm gì |
| --- | --- | --- |
| Event object `QueryExecuted` | Event chuỗi `illuminate.query` với 4 tham số rời | `QueryWatcher` nghe event chuỗi |
| `JobProcessing` / `JobProcessed` / `JobFailed` | Chỉ có `illuminate.queue.failed`, `illuminate.queue.stopping` | Decorate binding `queue.worker` |
| `CommandStarting` / `CommandFinished` | Chỉ có `artisan.start` | `artisan.start` + `register_shutdown_function` |
| `RequestHandled` | Không có | Terminable middleware prepend vào global stack |
| `Str::uuid()` | Không có | `HungLv\Telescope\Support\Uuid` |
| Package auto-discovery | Không có | Đăng ký provider bằng tay |
| Gate | Chưa có | Middleware `Authorize` + `Telescope::auth()` |
| Vue SPA + Laravel Mix | Không có build tooling | Blade thuần |

Schema (`telescope_entries`, `telescope_entries_tags`) và các khái niệm cốt lõi giữ nguyên như bản
gốc, nên khi nâng cấp Laravel sau này thói quen sử dụng vẫn còn nguyên giá trị.

## Yêu cầu

- PHP 5.4 trở lên
- Laravel 5.0.x
- Một database connection bất kỳ mà query builder hỗ trợ

Đã kiểm chứng trên Laravel 5.0.14 / PHP 7.0. Laravel 5.1–5.6 chưa test.

## Cài đặt

```bash
composer require hunglv-fs/laravel5-telescope
```

Thêm provider vào `config/app.php`:

```php
'providers' => [
    // ...
    'HungLv\Telescope\TelescopeServiceProvider',
],
```

Publish config + migration rồi migrate:

```bash
php artisan vendor:publish --provider="HungLv\Telescope\TelescopeServiceProvider"
php artisan migrate
```

Mở `/telescope`.

<details>
<summary>Cài không qua Composer (project quá cũ)</summary>

Đặt package vào ví dụ `packages/telescope`, rồi require autoloader kèm sẵn trong
`bootstrap/autoload.php`, ngay sau autoloader của Composer:

```php
require __DIR__.'/../packages/telescope/autoload.php';
```

Sau đó copy tay `config/telescope.php` và file migration vào ứng dụng.
</details>

## Ghi được những gì

| Watcher | Nội dung |
| --- | --- |
| `query` | SQL, bindings, thời lượng, connection, **file:dòng trong app** đã phát sinh query, tag `slow` / `duplicate` |
| `job` | tên, queue, trạng thái, số lần thử, thời lượng, peak memory, payload — kèm mọi query job đã chạy |
| `command` | dòng lệnh, thời lượng, peak memory, thống kê query, fatal error |
| `request` | method, URI, action, status, thời lượng, payload, headers, session, user, response |
| `exception` | class, message, file:dòng, stack trace, gom nhóm theo family hash |
| `log` / `cache` / `mail` | level + context / hit-missed-set-forget / subject + người nhận + nội dung |
| `event` | mọi event chuỗi (mặc định tắt vì dispatcher 5.0 rất ồn) |

## Debug batch job

Mỗi job được worker xử lý là một batch riêng: entry của job cộng với mọi query, log, cache nó gây ra.
Mở một job ở `/telescope/jobs` là thấy ngay nó bắn bao nhiêu query, hết bao nhiêu ms, câu nào lặp.

Job phải chạy qua `queue.worker` (`php artisan queue:work` hoặc `queue:listen`). Với driver `sync`,
job chạy luôn trong batch của request đã gọi nó — cũng là điều bạn muốn.

## Insights

`/telescope/insights`:

- **N+1 suspects** — cùng một *hình dạng* query chạy nhiều lần trong một batch, kèm file:dòng đã gọi.
  `in (?, ?)` và `in (?, ?, ?)` được coi là một hình dạng.
- **Query hotspots** — gom query gần đây theo hình dạng, xếp theo tổng thời gian đã đốt.
- **Slowest requests / jobs / commands.**

## Cấu hình

Toàn bộ nằm ở `config/telescope.php`.

| Key | Ý nghĩa |
| --- | --- |
| `enabled` | `TELESCOPE_ENABLED=false` thì không watcher nào được đăng ký |
| `path` | Đường dẫn dashboard, mặc định `telescope` |
| `limit` | Số entry tối đa mỗi batch, chặn job chạy 100k query làm nổ RAM |
| `storage.database.connection` | `null` = chốt theo `database.default` lúc boot; trỏ sang connection khác để không dính transaction của app |
| `ignore_paths` | Pattern `Str::is()`; dashboard luôn được bỏ qua |
| `watchers.query.slow` | Ngưỡng (ms) để gắn tag `slow` |
| `watchers.query.slow_only` | Chỉ ghi query chậm — cách rẻ để bật ngoài môi trường dev |
| `watchers.query.backtrace` | Truy ngược file/dòng trong app đã gọi query |
| `watchers.command.ignore` | Command không tự ghi entry (`queue:work`, `telescope:*`, ...) |
| `local_environments`, `allowed_ips` | Ai được mở dashboard |

## Giới hạn quyền truy cập

Dashboard lộ payload request, session và SQL, nên mặc định chỉ mở ở các environment trong
`local_environments`. Muốn mở cho người cụ thể ở nơi khác, đăng ký trong `boot()` của một provider:

```php
\HungLv\Telescope\Telescope::auth(function ($request) {
    return in_array($request->user()->email, ['ban@example.com']);
});
```

Lọc bớt entry trước khi lưu:

```php
\HungLv\Telescope\Telescope::filter(function ($entry) {
    return $entry->type !== 'query' || $entry->hasTag('slow');
});
```

Ghi tay một exception đã bắt:

```php
try {
    $this->import($row);
} catch (\Exception $e) {
    \HungLv\Telescope\Telescope::catchException($e, ['import', 'row:'.$row->id]);
}
```

## Dọn dữ liệu

```bash
php artisan telescope:prune --hours=48   # nên đặt vào cron
php artisan telescope:clear              # xoá sạch
```

## Vài đặc thù của 5.0 gặp phải khi làm

- **Exception không bị ghi hai lần.** 5.0 bind `Psr\Log\LoggerInterface` thẳng vào
  `$app['log']->getMonolog()`, nên `Handler::report()` ghi log bỏ qua `Illuminate\Log\Writer` và
  không bắn `illuminate.log`. Đó là lý do phải decorate binding `ExceptionHandler`.
- **Connection được chốt lúc boot.** `migrate --database=other` gọi `setDefaultConnection()`, ghi đè
  `database.default` toàn cục; resolve muộn thì Telescope sẽ ghi nhầm chỗ và mất entry.
- **Closure job vỡ trên PHP 7** (lỗi của Laravel 5.0, không phải của package): SuperClosure 1.x kéo
  theo php-parser có class tên `String`, reserved từ PHP 7. Dùng `'App\Foo@handle'` hoặc job object.

## Giới hạn đã biết

- Không realtime: dashboard là trang Blade tĩnh, F5 để xem mới.
- Không theo dõi Redis, model event, view render, HTTP client — 5.0 không phát event cho những thứ này.
- Không lấy được exit code của artisan (5.0 không có `CommandFinished`); chỉ phân biệt fatal error,
  và gắn tag `exception` khi có exception được report.
- Không có `telescope_monitoring` (tính năng "monitor tag" của bản gốc).

## Kiểm tra

Cả hai chạy độc lập, không cần dựng app:

```bash
php tests/smoke.php   # pipeline ghi nhận: batch, tag, N+1, cap, backtrace, row DB
composer install && php tests/views.php   # compile toàn bộ view bằng chính Blade 5.0
```

## Ghi công

Bản dựng độc lập cho Laravel 5.0, đi theo schema và kiến trúc watcher của
[laravel/telescope](https://github.com/laravel/telescope) (Taylor Otwell). Không liên kết với Laravel.

## Giấy phép

MIT — xem [LICENSE](LICENSE).
