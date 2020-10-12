# nova-jalali-metrics
Use this Trait to Convert Gregorian Dates to Jalali Dates for Nova Metric Trends.

```php
use Amidesfahani\NovaJalaliMetrics\JalaliTrend;
use Laravel\Nova\Metrics\Trend;

class UsersPerDay extends Trend
{
    use JalaliTrend;
}
