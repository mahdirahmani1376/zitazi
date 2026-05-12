## About Project

Ecommerce Price Monitoring & Crawling Platform

This is a distributed price monitoring system designed to automatically crawl ecommerce websites, extract product
pricing information, and deliver the data to an external service for storage and analysis.

The platform runs scheduled crawlers that scan target ecommerce sites daily and detect price updates across products.

It is built using a hybrid architecture combining Laravel for orchestration and APIs and Node.js with Puppeteer for
advanced scraping.

## Features

- Automated ecommerce price crawling
- Headless browser scraping with Puppeteer
- Dynamic content scraping support
- Distributed crawler architecture
- Scheduled crawling via Laravel scheduler
- Queue processing with Laravel Horizon
- Docker-based deployment
- Data export and processing pipelines
- Admin interface using Filament
- Integration with external APIs

## Architecture

                          Cron Scheduler
                                │
                                ▼
                        Laravel Application
                                │
                     ┌──────────┴──────────┐
                     ▼                     ▼
                Queue Workers       API / Admin Panel
                (Laravel Horizon)        (Filament)
                    │
                    ▼
                Node Scraper Service
                (Puppeteer + Cheerio)
                    │
                    ▼
                Ecommerce Websites
                    │
                    ▼
                Extracted Price Data
                    │
                    ▼
                External API / Storage Service

## Technology Stack

### Backend

- PHP 8.2
- Laravel 11
- Laravel Horizon
- Redis
- MySQL

### Scraping

- Node.js
- Puppeteer
- Puppeteer Extra (Stealth Plugin)
- Cheerio

### Infrastructure

- Docker
- Nginx
- Docker Compose

### Other Libraries

- Symfony DomCrawler
- Google API Client
- WooCommerce SDK
- Laravel Sanctum
- Laravel Excel
