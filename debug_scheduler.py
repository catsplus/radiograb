#!/usr/bin/env python3
"""
Debug scheduler to test cron trigger calculations
"""

from datetime import datetime
from apscheduler.triggers.cron import CronTrigger
import pytz

# Set Eastern timezone
eastern = pytz.timezone('America/New_York')
now = eastern.localize(datetime.now())

print(f"Current time: {now}")
print(f"Day of week: {now.strftime('%A')} (0=Monday, 6=Sunday in cron)")
print()

# Test Sunday Morning Coffeehouse cron pattern
cron_pattern = "0 8 * * 0"  # 8:00 AM on Sundays
minute, hour, day, month, day_of_week = cron_pattern.split()

print(f"Testing cron pattern: {cron_pattern}")
print(f"Parsed: minute={minute}, hour={hour}, day={day}, month={month}, day_of_week={day_of_week}")

trigger = CronTrigger(
    minute=minute,
    hour=hour, 
    day=day,
    month=month,
    day_of_week=day_of_week,
    timezone='America/New_York'
)

next_run = trigger.get_next_fire_time(None, now)
print(f"Next run calculated: {next_run}")

if next_run:
    print(f"Next run day: {next_run.strftime('%A, %Y-%m-%d %H:%M:%S')}")
    days_until = (next_run - now).days
    print(f"Days until next run: {days_until}")
else:
    print("No next run calculated")

# Test a few more patterns
test_patterns = [
    ("0 10 * * 1", "Monday 10 AM"),
    ("0 11 * * 1", "Monday 11 AM"), 
    ("0 8 * * 6", "Saturday 8 AM")
]

print("\nTesting other patterns:")
for pattern, description in test_patterns:
    minute, hour, day, month, day_of_week = pattern.split()
    trigger = CronTrigger(
        minute=minute,
        hour=hour,
        day=day, 
        month=month,
        day_of_week=day_of_week,
        timezone='America/New_York'
    )
    next_run = trigger.get_next_fire_time(None, now)
    if next_run:
        print(f"{description}: {next_run.strftime('%A, %Y-%m-%d %H:%M:%S')}")
    else:
        print(f"{description}: No next run")