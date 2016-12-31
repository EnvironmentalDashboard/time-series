#! /bin/bash

# This script installs the time series and its dependancies, which reside in other repos
# It assumes none of the other repos have been installed before (it overwrites the files and database)
# It is essentially the same script that is used to install CWD

# Our webpage's address is cardcoded in to the database in various places, so do a sed to replace them
read -p "Enter your domain name (this will be used to update the URLs of assets in the database): " DOMAIN
DOMAIN_ESC=$(sed 's/[\*\.]/\\&/g' <<<"$DOMAIN")
# See http://stackoverflow.com/a/5174368/2624391
sed -e "s|http://104\.131\.103\.232/oberlin|$DOMAIN_ESC|g" install.sql > install.sql.tmp && mv install.sql.tmp install.sql
read -p "Do you want to install Citywide Dashboard as well (y/n)? " REPLY
echo # for newline
echo "BuildingOS API information"
echo "=========================="
read -p "Enter client ID: " BOS_CLIENT_ID
read -p "Enter client secret: " BOS_CLIENT_SECRET
read -p "Enter username: " BOS_USERNAME
read -p "Enter password: " BOS_PASSWORD
echo
echo "MySQL server information"
echo "=========================="
read -p "Enter host: " MYSQL_HOST
read -p "Enter user: " MYSQL_USER
read -p "Enter password: " MYSQL_PASSWORD
read -p "Enter database name: " MYSQL_DB

# Directory structure
mkdir gauges
mkdir includes
mkdir scripts
mkdir prefs
mkdir time-series
cd time-series
git clone "https://github.com/EnvironmentalDashboard/time-series.git"
# Save the API information. Hopefully will be through OAUTH or something in the future
echo "INSERT INTO api (client_id, client_secret, username, password) VALUES($BOS_CLIENT_ID, $BOS_CLIENT_SECRET, $BOS_USERNAME, $BOS_PASSWORD);" >> "install.sql"
# Install db
mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" "-p$MYSQL_PASSWORD" "$MYSQL_DB" < install.sql
rm install.sql
cd ../gauges
git clone "https://github.com/EnvironmentalDashboard/gauges.git"
rm install.sql # Because we already did this
cd ../includes
git clone "https://github.com/EnvironmentalDashboard/includes.git"
cd ../scripts
git clone "https://github.com/EnvironmentalDashboard/scripts.git"
cd ../prefs
git clone "https://github.com/EnvironmentalDashboard/prefs.git"
if [ "$REPLY" == "y" ]; then
  cd ..
  mkdir cwd
  cd cwd
  git clone "https://github.com/EnvironmentalDashboard/citywide-dashboard.git"
  rm install.sql
fi

# Install cron jobs (have NOT tested yet!)
# http://stackoverflow.com/a/878647/2624391
PATH=pwd
# write out current crontab
crontab -l > mycron
#echo new cron into cron file
echo "*/2 * * * * php $PATH/scripts/jobs/minute.php" >> mycron
echo "*/15 * * * * php $PATH/scripts/jobs/quarterhour.php" >> mycron
echo "0 * * * * php $PATH/scripts/jobs/hour.php" >> mycron
echo "0 0 1 * * php $PATH/scripts/jobs/month.php" >> mycron
#install new cron file
crontab mycron
rm mycron
