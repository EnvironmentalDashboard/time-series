![Environmental Dashboard](http://104.131.103.232/oberlin/prefs/images/env_logo.png "Environmental Dashboard")

# Time Series

### About

This time series was developed to display resource consumption using data from the BuildingOS API. It features an empathetic charachter that reacts to the data. The behaviour of the charachter is determined by the mouse position over the chart and is interpreted by the `play()`, `play_movie()`, and `play_data()` functions. When the time series first loads, `play_data()` is called, which iterates over each data point and animates the charachters according to whether the current point (as indicated by the tracking ball) is high or low relative to the rest of the data. After a 3 second period of inaction, `play_data()` will call `play()`, which in turn either randomly calls `play_data()` again or `play_movie()`. The latter causes a short movie or animation to be played which is determined by how high/low the current data point is relative to the rest of the data. When the mouse moves, any work being done by the aforementioned functions is cancelled and the charachter will respond to the user moving their mouse through the data. If the mouse is idle for 3 seconds, `play()` is called, restarting the described process.

### Bugs and new features

If you have a feature request or have found a bug, [submit an issue](https://github.com/EnvironmentalDashboard/time-series/issues). To contribute to this project, submit a pull request.

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
