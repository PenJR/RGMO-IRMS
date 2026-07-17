# Inventory Forecasting Formulas

The inventory forecasting calculations are implemented in `app/Services/InventoryForecastingService.php`.

The system forecasts demand per inventory item using the last 180 days of stock-out transactions. Only `stock_out` transactions are counted as demand. Days without stock-out activity are treated as zero demand.

## Base Demand Series

For each item, the system builds a daily demand series:

```text
daily_demand[day] = sum(stock_out quantity for the item on that day)
```

If there is no stock-out transaction for a day:

```text
daily_demand[day] = 0
```

The forecast horizon is:

```text
forecast_days = 30
```

## No Demand History

If the total demand over the 180-day history is zero:

```text
projected_demand = 0
forecast_lower = 0
forecast_upper = 0
confidence_score = 0
forecast_model = "No demand history"
```

## Candidate Forecast Models

The system evaluates three candidate models for each item:

1. Croston-SBA
2. Weighted average
3. 90-day average

The model with the lowest backtest error is selected for the item.

## 1. 90-Day Average

This model uses the average daily usage from the most recent 90 days.

```text
daily_rate = sum(last 90 days demand) / 90
```

The 30-day forecast is:

```text
projected_demand = daily_rate * 30
```

## 2. Weighted Average

This model gives more importance to recent demand.

It splits the most recent 90 days into two 45-day periods:

```text
recent_average = sum(last 45 days demand) / 45
prior_average = sum(previous 45 days demand) / 45
```

The weighted daily rate is:

```text
daily_rate = (recent_average * 0.70) + (prior_average * 0.30)
```

The 30-day forecast is:

```text
projected_demand = daily_rate * 30
```

## 3. Croston-SBA

Croston-SBA is used for intermittent demand, where an item is requested only occasionally.

The smoothing value is:

```text
alpha = 0.2
```

The method tracks two values:

```text
demand_size = estimated non-zero demand quantity
interval = estimated gap between non-zero demand days
```

For each day with non-zero demand:

```text
gap = current_non_zero_day_index - previous_non_zero_day_index
```

The estimates are updated as:

```text
demand_size = demand_size + alpha * (actual_demand - demand_size)
interval = interval + alpha * (gap - interval)
```

The Croston-SBA daily rate is:

```text
daily_rate = (1 - alpha / 2) * (demand_size / interval)
```

Since `alpha = 0.2`, this becomes:

```text
daily_rate = 0.9 * (demand_size / interval)
```

The 30-day forecast is:

```text
projected_demand = daily_rate * 30
```

## Model Backtesting

The system backtests each model using up to three completed 30-day windows.

For each backtest window:

```text
training_data = demand history before the test window
actual = sum(actual demand during the 30-day test window)
predicted = model(training_data) * 30
absolute_error = abs(actual - predicted)
```

The normalized error is:

```text
normalized_error =
sum(absolute_errors) / max(1, total_actual, total_predicted)
```

The selected model is the model with the lowest `normalized_error`.

If two models have the same error, the tie-break order is:

```text
Croston-SBA
Weighted average
90-day average
```

The backtest error percentage shown by the system is:

```text
backtest_error_percent = normalized_error * 100
```

The displayed value is rounded to one decimal place and capped at 999%.

## Forecast Range

The system creates a planning range using the forecast point estimate plus a margin.

First, it groups the 180-day history into 30-day monthly totals:

```text
monthly_total = sum(demand in each complete 30-day block)
```

Then it calculates the sample standard deviation:

```text
mean = sum(monthly_totals) / count(monthly_totals)

standard_deviation =
sqrt(sum((monthly_total - mean)^2) / (count(monthly_totals) - 1))
```

It also calculates the mean backtest error:

```text
mean_backtest_error = sum(absolute_errors) / count(absolute_errors)
```

The forecast margin is:

```text
margin = max(1.28 * standard_deviation, mean_backtest_error)
```

The lower and upper forecast range is:

```text
forecast_lower = max(0, projected_demand - margin)
forecast_upper = projected_demand + margin
```

In the final display:

```text
forecast_lower = floor(forecast_lower)
forecast_upper = ceil(forecast_upper)
```

## Average Daily Usage

The average daily usage shown in the table is based on the selected forecast:

```text
average_daily_usage = projected_demand / 30
```

The displayed value is rounded to two decimal places.

## Days Until Stockout

If average daily usage is greater than zero:

```text
days_until_stockout = floor(current_stock / average_daily_usage)
```

If average daily usage is zero:

```text
days_until_stockout = null
```

The page displays this as `No trend`.

## Recommended Order

The recommended order tries to cover the forecasted 30-day demand and keep the item at its minimum stock level.

```text
recommended_order = max(0, projected_demand + minimum_stock - current_stock)
```

## Demand Change Percent

The system compares recent 90-day demand against the previous 90-day demand.

```text
current_usage = sum(last 90 days demand)
previous_usage = sum(previous 90 days demand)
```

If previous usage is greater than zero:

```text
demand_change_percent =
((current_usage - previous_usage) / previous_usage) * 100
```

If previous usage is zero and current usage is greater than zero:

```text
demand_change_percent = 100
```

If both previous and current usage are zero:

```text
demand_change_percent = 0
```

The displayed value is rounded to one decimal place.

## Confidence Score

The confidence score is based on backtested accuracy and how much non-zero demand history exists.

```text
accuracy = max(0, 1 - min(1, normalized_error))
```

```text
history_reliability = min(1, non_zero_demand_days / 6)
```

The confidence score is:

```text
confidence_score =
100 * accuracy * (0.25 + (0.75 * history_reliability))
```

The final confidence score is capped at 95:

```text
confidence_score = min(95, confidence_score)
```

The displayed value is rounded to the nearest whole number.

## Risk Level

Each forecast is classified as `critical`, `watch`, or `stable`.

An item is `critical` if:

```text
current_stock <= minimum_stock
```

or:

```text
days_until_stockout <= 14
```

An item is `watch` if it is not critical and any of these are true:

```text
forecast_upper >= current_stock
projected_demand >= current_stock
days_until_stockout <= 30
```

Otherwise, the item is:

```text
stable
```

## Summary Totals

The dashboard summary values are totals across all item forecasts.

```text
total_projected_demand = sum(projected_demand for all items)
total_forecast_lower = sum(forecast_lower for all items)
total_forecast_upper = sum(forecast_upper for all items)
critical_items = count(items where risk = critical)
watch_items = count(items where risk = watch)
stable_items = count(items where risk = stable)
recommended_orders = count(items where recommended_order > 0)
```

The overall confidence score is the average confidence score of items with demand history:

```text
overall_confidence_score =
average(confidence_score for items where forecast_model != "No demand history")
```

If no item has demand history:

```text
overall_confidence_score = 0
```

## Category Demand

Category demand groups item forecasts by category:

```text
category_projected_demand =
sum(projected_demand for all items in the category)
```

The system displays the top six categories sorted by projected demand.
