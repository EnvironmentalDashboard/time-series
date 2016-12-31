![Environmental Dashboard](http://104.131.103.232/oberlin/prefs/images/env_logo.png "Environmental Dashboard")

# Time Series

### About

This time series was developed to display resource consumption using data from the BuildingOS API. It features an empathetic charachter that reacts to the data.

### Installation

To install the time series on your own you need a server with PHP, MySQL, shell access, and BuildingOS API access<sup>[1](#f1)</sup>. For the time series to recieve resource consumption data, other scripts from different repositories need to be installed<sup>[2](#f2)</sup>. `install.sh` is an interactive shell script that will install the necessary dependencies and database. Read it to understand how this app is structured. Because [City-wide Dashboard](https://github.com/EnvironmentalDashboard/time-series) is built on top of the framework the time series uses, the shell script will also ask you if you want to install it as well. Once installed, the directory structure will be

/time-series - Where the time series display will be installed

/[gauges](https://github.com/EnvironmentalDashboard/gauges) - The gauges of CWD are a standalone project

/[scripts](https://github.com/EnvironmentalDashboard/scripts) - Scripts to be run by cron to collect data from Lucid

/[includes](https://github.com/EnvironmentalDashboard/includes) - Classes required by the gauges, scripts, and time series

/[cwd](https://github.com/EnvironmentalDashboard/citywide-dashboard) - Where CWD is optionally cloned to

/[prefs](https://github.com/EnvironmentalDashboard/prefs) - Preferences page for managing CWD and time series

---

<a name="f1">1</a>: I'm not sure how you obtain this

<a name="f2">2</a>: If you have another mechanism of obtaining data, you could just clone this repo instead of using the install script so long as you're matching the format the database expects. For more information, read over the install script.
