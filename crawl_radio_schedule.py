"""
Crawls radio station websites to find and parse programming schedules.

This script is a standalone utility for extracting schedule information from
HTML pages. It attempts to identify schedule URLs and then parses the HTML
content using predefined patterns for common schedule formats.

Key Variables:
- `base_url`: The base URL of the radio station's website.
- `schedule_url`: The URL of the identified schedule page.

Inter-script Communication:
- This script is a standalone utility and does not directly interact with other
  backend services or the database.
"""

from bs4 import BeautifulSoup
from urllib.parse import urljoin
import re
from datetime import datetime, timedelta
import sys

HEADERS = {
    "User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36"
}

def fetch_html(url):
    try:
        resp = requests.get(url, headers=HEADERS, timeout=10)
        resp.raise_for_status()
        return BeautifulSoup(resp.text, "html.parser")
    except requests.exceptions.RequestException as e:
        raise Exception(f"Failed to fetch {url}: {e}")

def find_schedule_url(base_url):
    soup = fetch_html(base_url)
    links = soup.find_all("a", href=True)[:300]  # Cap to avoid long hangs
    candidate_urls = []

    for a in links:
        text = a.get_text(" ", strip=True).lower()
        href = a["href"]
        full_url = urljoin(base_url, href)
        score = 0
        if "schedule" in text or "schedule" in href:
            score += 3
        if "program" in text or "program" in href:
            score += 2
        if "calendar" in text or "calendar" in href:
            score += 2
        if "show" in text or "show" in href:
            score += 1
        if score > 0:
            candidate_urls.append((score, full_url))

    if not candidate_urls:
        return None

    return sorted(candidate_urls, key=lambda x: -x[0])[0][1]

def parse_weru_style(soup):
    events = []
    for day_div in soup.select("div.calendar-day"):
        header = day_div.find("h3")
        weekday = header.get_text(strip=True) if header else None
        for ev in day_div.select("div.calendar-event"):
            name = ev.find("span", class_="calendar-event-title").get_text(strip=True)
            time_text = ev.find("span", class_="calendar-event-time").get_text(strip=True)
            m = re.match(r"(\d{1,2}:\d{2}\s*[ap]m)\s*-\s*(\d{1,2}:\d{2}\s*[ap]m)", time_text, re.I)
            start, end = (m.group(1), m.group(2)) if m else (None, None)
            desc_tag = ev.find("div", class_="calendar-event-description")
            description = desc_tag.get_text(" ", strip=True) if desc_tag else ""
            events.append({
                "name": name,
                "weekday": weekday,
                "start": start,
                "end": end,
                "description": description
            })
    return events if events else None

def parse_wehc_style(soup):
    table = soup.find("table")
    if not table:
        return None

    header_cells = table.find("tr").find_all("th")[1:]
    weekdays = [cell.get_text(strip=True) for cell in header_cells]
    time_rows = table.find_all("tr")[1:]
    events = []

    for tr in time_rows:
        cells = tr.find_all(["td", "th"])
        if len(cells) <= 1:
            continue
        time_label = cells[0].get_text(strip=True)
        try:
            row_time = datetime.strptime(time_label, "%I:%M %p")
        except ValueError:
            continue

        col_idx = 0
        for cell in cells[1:]:
            rowspan = int(cell.get("rowspan", 1))
            name = cell.get_text(strip=True)
            if name:
                start_time = row_time
                end_time = start_time + timedelta(minutes=30 * rowspan)
                events.append({
                    "name": name,
                    "weekday": weekdays[col_idx],
                    "start": start_time.strftime("%I:%M %p"),
                    "end": end_time.strftime("%I:%M %p"),
                    "description": ""
                })
            col_idx += 1
    return events if events else None

def detect_and_parse_schedule(schedule_url):
    soup = fetch_html(schedule_url)
    for parser in (parse_weru_style, parse_wehc_style):
        try:
            result = parser(soup)
            if result:
                return result
        except Exception:
            continue
    return []

def crawl_and_parse_schedule(station_url):
    print(f"🔍 Crawling {station_url}")
    schedule_url = find_schedule_url(station_url)
    if not schedule_url:
        raise Exception("No schedule/programming page found.")
    print(f"📅 Found schedule URL: {schedule_url}")
    events = detect_and_parse_schedule(schedule_url)
    if not events:
        raise Exception("Could not parse known schedule format.")
    return events

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python crawl_radio_schedule.py <station_homepage_url>")
        sys.exit(1)

    station_homepage = sys.argv[1]
    try:
        events = crawl_and_parse_schedule(station_homepage)
        for e in events:
            print(f"{e['weekday']} | {e['start']}–{e['end']} | {e['name']}")
            if e['description']:
                print("  ", e["description"])
    except Exception as ex:
        print("❌ Error:", ex)
