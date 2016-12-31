-- MySQL dump 10.13  Distrib 5.7.16, for Linux (x86_64)
--
-- Host: localhost    Database: oberlin_environmentaldashboard
-- ------------------------------------------------------
-- Server version 5.7.16-0ubuntu0.16.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `analytics`
--

DROP TABLE IF EXISTS `analytics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `referer` varchar(255) NOT NULL,
  `loc` varchar(255) NOT NULL,
  `coords` varchar(20) NOT NULL,
  `browser` varchar(255) NOT NULL,
  `platform` varchar(255) NOT NULL,
  `recorded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=264 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `api`
--

DROP TABLE IF EXISTS `api`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` varchar(255) NOT NULL,
  `client_secret` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `token_updated` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `buildings`
--

DROP TABLE IF EXISTS `buildings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `buildings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bos_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `building_type` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `loc` varchar(255) NOT NULL,
  `area` mediumint(8) unsigned NOT NULL,
  `occupancy` smallint(5) unsigned NOT NULL,
  `floors` tinyint(3) unsigned NOT NULL,
  `img` varchar(255) NOT NULL,
  `custom_img` varchar(255) NOT NULL,
  `org_url` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bos_id` (`bos_id`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gauges`
--

DROP TABLE IF EXISTS `gauges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gauges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meter_id` int(11) NOT NULL,
  `data_interval` varchar(255) NOT NULL,
  `color` varchar(255) NOT NULL,
  `bg` varchar(255) NOT NULL,
  `height` smallint(4) NOT NULL,
  `width` smallint(4) NOT NULL,
  `font_family` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `title2` varchar(255) NOT NULL,
  `border_radius` tinyint(1) NOT NULL,
  `rounding` tinyint(1) NOT NULL,
  `ver` enum('html','svg') NOT NULL,
  `units` varchar(255) NOT NULL,
  `start` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `meters`
--

DROP TABLE IF EXISTS `meters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `meters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bos_uuid` varchar(255) NOT NULL,
  `building_id` int(11) NOT NULL,
  `source` enum('buildingos','user') NOT NULL,
  `name` varchar(255) NOT NULL,
  `url` varchar(2000) NOT NULL,
  `building_url` varchar(2000) NOT NULL,
  `current` decimal(9,3) NOT NULL,
  `units` varchar(255) NOT NULL,
  `last_updated` int(10) NOT NULL,
  `num_using` int(11) unsigned NOT NULL,
  `for_orb` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bos_uuid` (`bos_uuid`)
) ENGINE=InnoDB AUTO_INCREMENT=894 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `meter_data`
--

DROP TABLE IF EXISTS `meter_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `meter_data` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `meter_id` int(11) NOT NULL,
  `value` decimal(9,3) DEFAULT NULL,
  `recorded` int(10) NOT NULL,
  `resolution` enum('live','quarterhour','hour','day','month','other') NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15421336 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2016-12-30  3:03:56
-- MySQL dump 10.13  Distrib 5.7.16, for Linux (x86_64)
--
-- Host: localhost    Database: oberlin_environmentaldashboard
-- ------------------------------------------------------
-- Server version 5.7.16-0ubuntu0.16.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `cwd_bos`
--

DROP TABLE IF EXISTS `cwd_bos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cwd_bos` (
  `squirrel` int(11) NOT NULL,
  `fish` int(11) NOT NULL,
  `water_speed` int(11) NOT NULL,
  `electricity_speed` int(11) NOT NULL,
  `landing_messages` int(11) NOT NULL,
  `electricity_messages` int(11) NOT NULL,
  `gas_messages` int(11) NOT NULL,
  `stream_messages` int(11) NOT NULL,
  `water_messages` int(11) NOT NULL,
  `weather_messages` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cwd_bos`
--

LOCK TABLES `cwd_bos` WRITE;
/*!40000 ALTER TABLE `cwd_bos` DISABLE KEYS */;
INSERT INTO `cwd_bos` VALUES (2,3,3,2,1,2,1,4,3,1);
/*!40000 ALTER TABLE `cwd_bos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cwd_landscape_components`
--

DROP TABLE IF EXISTS `cwd_landscape_components`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cwd_landscape_components` (
  `component` varchar(255) NOT NULL,
  `pos` varchar(255) NOT NULL DEFAULT '0,0',
  `widthxheight` varchar(255) NOT NULL DEFAULT '0x0',
  `title` varchar(255) NOT NULL,
  `link` varchar(2000) NOT NULL,
  `img` varchar(2000) NOT NULL,
  `text` varchar(1000) NOT NULL,
  `text_pos` varchar(255) NOT NULL DEFAULT '0,0',
  `order` tinyint(3) unsigned NOT NULL,
  `removable` tinyint(1) NOT NULL DEFAULT '1',
  `hidden` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`component`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cwd_landscape_components`
--

LOCK TABLES `cwd_landscape_components` WRITE;
/*!40000 ALTER TABLE `cwd_landscape_components` DISABLE KEYS */;
INSERT INTO `cwd_landscape_components` VALUES ('agriculture','1117,257.5','123x65','Agriculture','http://oberlindashboard.org/wiki.php#1f','http://104.131.103.232/oberlin/cwd/img/agriculture.png','Although farmland is rapidly turning into houses, agriculture is still the largest industry in Lorain County. While corn and soybeans cover the largest area, local fruits and vegetables are easily available and very tasty.','900,250',0,0,0),('bridge','-150,300','721x680','Bridge','','http://104.131.103.232/oberlin/cwd/img/bridge.svg','','0,0',1,0,1),('building_cluster','876,291','293x189','Buildings','','http://104.131.103.232/oberlin/cwd/img/buildings.svg','','0,0',1,0,1),('buoy','500,50','226x486','Buoy','http://www.google.com','http://104.131.103.232/oberlin/cwd/img/buoy.svg','','500,50',1,0,1),('city','1069,145.5','144x68','Urbanization and Cleveland','http://oberlindashboard.org/wiki.php#1g','http://104.131.103.232/oberlin/cwd/img/city.png','The bigger cities around Oberlin like Elyria, Lorain and Cleveland are part of the ','900,150',0,0,0),('college','876,291','321x272','Oberlin College ','http://oberlindashboard.org/wiki.php#1i','http://104.131.103.232/oberlin/cwd/img/college.png','Oberlin College was the first institution in the US to admit african americans, the first to admit women and is working closely with the city to build an ecologically, socially and economically sustainable model of a post-fossil fuel community.','900,480',0,0,0),('houses','519,583.5','435x231','Your Home','http://oberlindashboard.org/wiki.php#1k','http://104.131.103.232/oberlin/cwd/img/houses.png','The decisions each of us make every day in our homes, schools and workplaces directly affect our community and the environment. We are all connected by these choices.','800,600',3,0,0),('industry','79,292.5','228x90','Electricity Production','http://oberlindashboard.org/wiki.php#1a','http://104.131.103.232/oberlin/cwd/img/power_plant.png','Oberlin Municipal Light and Power (OMLPS) manages the flow of electricity from power generation facilities (using landfill gas, hydroelectric, wind, solar, coal and nuclear) over the electrical ','100,350',0,0,0),('park','491,431','408x177','Town Square','http://oberlindashboard.org/wiki.php#1j','http://104.131.103.232/oberlin/cwd/img/park.png','Whether you enjoy the many cultural or musical events, or just lying in the grass under trees with friends, this 13-acre square connects Oberlin residents to each other and our environment.','700,550',2,0,0),('reservoir','844,226','152x50','Drinking Water','http://oberlindashboard.org/wiki.php#1b','http://104.131.103.232/oberlin/cwd/img/reservoir.png','The water you use in Oberlin is collected from the West branch of the Black River, into a reservoir. It is then filtered, pumped, and stored in water towers until you turn on your tap.','950,250',0,0,0),('river_click','0,0','0x0','Watershed','http://oberlindashboard.org/wiki.php#1c','','A watershed is an area of land that drains to a single body of water. Rain and snow that fall on Oberlin drain into the Plum Creek, which then flows into the Black River.','200,900',0,0,0),('toledocityicon','310,150','240x430','Toledo icon','','http://104.131.103.232/oberlin/cwd/img/toledocityicon.svg','','0,0',1,0,1),('town','289,213','398x319','The Town of Oberlin','http://oberlindashboard.org/wiki.php#1h','http://104.131.103.232/oberlin/cwd/img/town.png','The City of Oberlin and Oberlin College were founded in 1833. Oberlin is building on its legacy as a leader on issues of civil rights and justice through commitments to environmental sustainability.','500,400',1,0,0),('water_tower','728,163.5','130x162','Drinking Water','http://oberlindashboard.org/wiki.php#1b','http://104.131.103.232/oberlin/cwd/img/water_tower.png','The water you use in Oberlin is collected from the West branch of the Black River, into a reservoir. It is then filtered, pumped, and stored in water towers until you turn on your tap.','800,200',0,0,0),('water_treatment','80,622','376x221','Wastewater Treatment Plant','http://oberlindashboard.org/wiki.php#1d','http://104.131.103.232/oberlin/cwd/img/watertreatment.png','Dirty water from homes and businesses flows through underground pipes to Oberlin’s wastewater treatment plant where it is cleaned and released into the Plum Creek.','150,700',0,0,0),('zoo_colored','1050,190','172x141','Zoo','','http://104.131.103.232/oberlin/cwd/img/zoo-colored.svg','','1117,195',1,0,1);
/*!40000 ALTER TABLE `cwd_landscape_components` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cwd_messages`
--

DROP TABLE IF EXISTS `cwd_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cwd_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource` enum('landing','electricity','gas','stream','water','weather') NOT NULL,
  `message` varchar(1000) NOT NULL,
  `prob1` tinyint(3) NOT NULL DEFAULT '10',
  `prob2` tinyint(3) NOT NULL DEFAULT '10',
  `prob3` tinyint(3) NOT NULL DEFAULT '10',
  `prob4` tinyint(3) NOT NULL DEFAULT '10',
  `prob5` tinyint(3) NOT NULL DEFAULT '10',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cwd_messages`
--

LOCK TABLES `cwd_messages` WRITE;
/*!40000 ALTER TABLE `cwd_messages` DISABLE KEYS */;
INSERT INTO `cwd_messages` VALUES (1,'landing','Welcome to Oberlin’s Environmental Dashboard.  The Energy Squirrel and Wally the Walleye will tell you about current environmental conditions in Oberlin and actions you can take.  Choose a resource or press the play button to start.',10,10,10,10,10),(3,'electricity','Oy! We have been using way too much energy.',0,0,0,0,10),(4,'electricity','Ouch! Energy use has increased.',0,0,0,10,0),(5,'electricity','Doing OK. Energy use has stayed the same.',0,0,10,0,0),(6,'electricity','Good job! We have decreased energy use.',0,10,0,0,0),(7,'electricity','Fantastic! We are conserving a lot of energy.',10,0,0,0,0),(8,'water','Oy! We have been using way too much water.',0,0,0,0,10),(9,'water','Ouch! Water consumption has increased.',0,0,0,10,0),(10,'water','Doing OK. Water use has stayed the same.',0,0,10,0,0),(11,'water','Good job! We have decreased water consumption.',0,10,0,0,0),(12,'water','Fantastic! We are conserving a lot of water.',10,0,0,0,0),(13,'stream','It\'s been super wet. Plum Creek is raging.',0,0,0,0,10),(14,'stream','Rainy Oberlin means Plum Creek is deeper.',0,0,0,10,0),(15,'stream','Plum Creek\'s water depth has not changed.',0,0,10,0,0),(16,'stream','Getting dryer. Plum Creek\'s depth is decreasing.',0,10,0,0,0),(17,'stream','Plum Creek\'s water depth is extremely low. ',10,0,0,0,0),(18,'weather','Air temperature has increased considedrably over this time period',0,0,0,0,10),(19,'weather','Air temperature has increased over this time period',0,0,0,10,0),(20,'weather','Air temperature has not changed over this time period',0,0,10,0,0),(21,'weather','Air temperature has decreased during this time period',0,10,0,0,0),(22,'weather','Air temperature has decreased considerably during this time period',10,0,0,0,0),(23,'electricity','Try hang drying your clothing instead of using a  dryer. Your dryer uses enough energy in one load to power a ceiling fan for 33 hours!',5,5,5,5,5),(24,'water','Replacing shower heads with low-flow units saves water and money.  One old shower head replaced with a low-flow alternative could easily save you 1,825 gallons of water annually--enough water to fill almost 50 full bath tubs!',5,5,5,5,5),(25,'stream','Make sure you don\'t add too much fertilizer to your lawn or garden.  Excess fertilizers will run off during rain and wash into the Plum Creek and Black River creating havoc for organisms trying to survive in those habitats.',5,5,5,5,5),(26,'landing','Welcome to Oberlin\'s Environmental Dashboard.  Try exploring our webpage by scrolling over different parts of the picture below.',7,7,7,7,7),(27,'landing','Welcome to the Bioregional Dashboard.  Explore current environmental conditions in Oberlin by clicking on different icons in the picture below.  \r\n',5,5,5,5,5),(28,'landing','Welcome to Oberlin\'s Bioregional Dashboard.  Did you know that less than 1% of the water on Earth is readily accessible to humans?  Click on the icons below to see how water and electricity flow through our city\'s environment.\r\n',6,6,6,6,6),(29,'landing','Welcome to Oberlin\'s Environmental Resource Dashboard.  Did you know that the Great Lakes hold 20% of the Earth\'s readily accessible freshwater?  Click on the play button below to learn more.',6,6,6,6,6),(30,'landing','Good day and welcome to Oberlin\'s Bioregional Dashboard! Click on the icons below to learn more about the existing environmental conditions in Oberlin.',4,4,4,4,4),(31,'electricity','The top three consumers of electricity in Oberlin are Oberlin College, The Federal Aviation Administration, and Lorain County Joint Vocational School.',3,3,3,3,3),(32,'electricity','A kilowatt-hour of electricity is enough energy to make three brews of coffee.',9,9,9,9,9),(33,'electricity','Greenhouse gases trap heat in the Earth\'s atmosphere like a blanket. The most common human-produced greenhouse gases are carbon dioxide and methane.',5,5,5,5,5),(34,'landing','Energy units can be confusing. Kilowatt-hour is to gallon as kilowatt is to gallon-per-minute.',8,8,8,8,8),(35,'electricity','Baseload power is the amount of electricity that needs to be constantly generated to satisfy the minimum demands of the community.',7,7,7,7,7),(36,'electricity','Space heating and air conditioning consume the most energy in homes. Together they equal almost half of the electricity used in homes.  Using a programmable thermostat can help reduce these costs.',5,5,5,5,5),(37,'electricity','In Oberlin, the peak load hours of electricity use in the spring, summer, and fall are between the hours of 2PM and 6PM.  In the winter, the peak occurs between the hours of 6AM and 10AM.  ',6,6,6,6,6),(38,'electricity','Ohioans pay on average 8 cents per kill-watt hour.',8,8,8,8,8),(39,'water','Oberlin’s drinking water is pumped from the west branch of the Black River into a reservoir and then carefully cleaned and filtered before it is delivered to your home.',8,8,8,8,8),(40,'water','One out of every six gallons of water that is pumped into water mains by U.S. utilities simply leaks away back into the ground.',8,8,8,8,8),(41,'water','Each day 1.8 million children die worldwide from lack of water or from diseases contracted from tainted drinking water.',7,7,7,7,7),(42,'water','The Oberlin reservoir on Parsons Road can hold 386,000,000 gallons of water--that\'s about a 15 month supply if no water is added.',5,5,5,5,5),(43,'water','The Oberlin Freshwater Treatment Plant cleans about 850,000 gallons of water each day.  That\'s 170 gallons for each resident.',6,6,6,6,6),(44,'stream','Turbidity is a measure of water clarity.  Soil eroding from fields and other particles in the water increase turbidity and can be unhealthy for stream life.',8,8,8,8,8),(45,'stream','Lake Erie holds a volume of 119 cubic miles of water.  That\'s 129 trillions gallons--enough to provide freshwater for all businesses and residents of the United States for 315 days.',8,8,8,8,8),(46,'weather','Weather in the Oberlin area is influenced by our temperate latitude but also by the proximity of Lake Erie, which moderates temperature and adds moisture to the atmosphere.',8,8,8,8,8),(47,'weather','Clouds can trap heat radiating from the Earth\'s surface which can actually increase the local ground temperature.  Clear, cloudless nights typically make for cooler conditions.',7,7,7,7,7),(48,'weather','Oberlin, Ohio annually averages about 36 inches of precipitation.  That includes rain, sleet, snow, and hail.',5,5,5,5,5),(49,'weather','Weather is the prevailing conditions of the atmosphere over a short period of time, and climate is how the atmosphere \"behaves\" over relatively longer periods of time.',9,9,9,9,9);
/*!40000 ALTER TABLE `cwd_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cwd_states`
--

DROP TABLE IF EXISTS `cwd_states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cwd_states` (
  `resource` varchar(255) NOT NULL,
  `gauge1` int(11) NOT NULL,
  `gauge2` int(11) NOT NULL,
  `gauge3` int(11) NOT NULL,
  `gauge4` int(11) NOT NULL,
  `on` tinyint(1) NOT NULL,
  PRIMARY KEY (`resource`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cwd_states`
--

LOCK TABLES `cwd_states` WRITE;
/*!40000 ALTER TABLE `cwd_states` DISABLE KEYS */;
INSERT INTO `cwd_states` VALUES ('electricity',5,19,6,1,1),('gas',1,1,1,1,0),('landing',2,16,4,1,1),('stream',4,10,11,12,1),('water',17,18,6,15,1),('weather',1,8,13,9,1);
/*!40000 ALTER TABLE `cwd_states` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `time_series`
--

DROP TABLE IF EXISTS `time_series`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `time_series` (
  `name` varchar(255) NOT NULL,
  `length` int(10) unsigned NOT NULL COMMENT 'In milliseconds',
  `bin1` tinyint(1) NOT NULL,
  `bin2` tinyint(1) NOT NULL,
  `bin3` tinyint(1) NOT NULL,
  `bin4` tinyint(1) NOT NULL,
  `bin5` tinyint(1) NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `time_series`
--

LOCK TABLES `time_series` WRITE;
/*!40000 ALTER TABLE `time_series` DISABLE KEYS */;
INSERT INTO `time_series` VALUES ('SQ_Emot_1-2_FootTapAkimbo',5510,5,3,0,0,0),('SQ_Emot_1-2_FootTapXdPaws',4270,5,3,0,0,0),('SQ_Emot_1-2_HandsOverMouthDroop',10360,8,7,2,0,0),('SQ_Emot_1-2_OhNoDeflated',8650,8,7,1,0,0),('SQ_Emot_1-2_WorldBeneathFallingApart',21660,8,7,1,0,0),('SQ_Emot_1_AlarmedJump',12450,5,2,0,0,0),('SQ_Emot_4-5_BlowKisses',18380,5,5,5,5,5),('SQ_Emot_4-5_hooray',0,0,0,2,7,8),('SQ_Emot_5_JumpSpinShadesThumbs',13540,0,0,1,5,10),('SQ_Fill_DefaultStance',10580,5,5,5,5,5),('SQ_Fill_NeutralActionsEyeroll',18160,5,5,5,5,5),('SQ_Fill_NeutralActionsScratch',9580,5,5,5,5,5),('SQ_Fill_NeutralActionsTotal_All',30200,5,5,5,5,5),('SQ_Idea_AC',10580,5,5,4,1,1),('SQ_Idea_BulbReplace',11060,5,5,4,3,3),('SQ_Idea_Clothesline',10360,5,5,5,5,5),('SQ_Idea_LightOff',16670,5,5,3,0,0),('SQ_Point_1_UrgentAlarmed',4560,10,0,0,0,0),('SQ_Point_2_AlarmedAndLookUp',5940,0,10,0,0,0),('SQ_Point_3_Ahoy',14400,0,0,10,0,0),('SQ_Point_3_Neutral',6340,0,0,10,0,0),('SQ_Point_4-5_Disco',6190,0,0,0,0,10),('SQ_Point_4-5_Happy',5270,0,0,0,10,0),('SQ_Story_FortuneBikeRide',32110,5,5,5,5,5),('Walley_Random_EatSeawdLickChopsFade',0,5,5,5,5,5),('Wally_BG_W_BubblesAndPlants_High',0,0,10,0,0,0),('Wally_BG_W_BubblesAndPlants_Highest',0,10,0,0,0,0),('Wally_BG_W_BubblesAndPlants_Neutrall',0,0,0,10,10,10),('Wally_Emo_FrontHappiest',0,0,0,0,0,10),('Wally_Emo_FrontHappySwimTowardsUs',0,0,0,2,4,7),('Wally_Emo_FrontMad',0,4,2,0,0,0),('Wally_Emo_FrontMadLookUp',0,6,2,0,0,0),('Wally_Emo_FrontNeutral',0,0,0,10,0,0),('Wally_Emo_FrontScared',0,9,7,0,0,0),('Wally_Emo_FrontScaredWBubbles',0,10,7,0,0,0),('Wally_Emo_FrontSquinty',0,2,1,0,0,0),('Wally_Emo_FrontSquintyLessMovement',0,2,1,0,0,0),('Wally_Emo_HappyCheekyWink2',0,0,0,2,3,6),('Wally_Emo_HappyFrontTurn2Gauge',0,0,0,2,2,4),('Wally_Emo_HappySwimUp2Flip',0,0,0,0,0,10),('Wally_Emo_MadFront',0,2,1,0,0,0),('Wally_Emo_MadFrontSquinty',0,2,1,0,0,0),('Wally_Emo_Neutral',0,0,0,10,0,0),('Wally_Emo_PointNeutralAlt',0,0,0,10,0,0),('Wally_Emo_SadTugHelp',0,5,3,0,0,0),('Wally_Emo_ScaredFront',0,8,6,0,0,0),('Wally_EndStory_HappyFrontTurnWink',0,10,10,10,10,10),('Wally_Point_2FrontAndBack2PointNeutral',0,0,0,10,0,0),('Wally_Point_34CheekMadSwimAway',0,4,4,5,2,0),('Wally_Point_34LookAtGaugeNAtUs',0,7,7,0,0,0),('Wally_Point_AltHappyFrontTo34LookAtGauge',0,0,0,0,8,10),('Wally_Point_AngrySlow',0,6,7,3,0,0),('Wally_Point_Frantic',0,8,4,0,0,0),('Wally_Point_Frantic2',0,8,4,0,0,0),('Wally_Point_FrontHelp',0,7,5,0,0,0),('Wally_Point_Happy',0,0,0,2,10,10),('Wally_Point_HappySwimInFromTop',0,0,0,2,8,10),('Wally_Point_HappySwimOffTop',0,0,0,2,8,10),('Wally_Point_MadFast',0,5,5,3,0,0),('Wally_Point_MadSlow',0,5,5,3,0,0),('Wally_Point_SadLethargic',0,8,7,0,0,0),('Wally_Point_SemiSad',0,0,4,4,0,0),('Wally_Point_SemiSadWTurn2Us',0,0,5,4,0,0),('Wally_Point_Squinty',0,7,3,0,0,0),('Wally_RandomSwimTowardsNUp',0,3,3,3,3,3),('Wally_Random_ChaseMinnows',0,7,7,7,7,7),('Wally_Random_DartersSwimL2R',0,8,8,8,8,8),('Wally_Random_DartersSwimR2L',0,7,7,7,7,7),('Wally_Random_EatSeawdSwimOff',0,5,5,5,5,5),('Wally_Random_Fr2SwimOff',0,7,7,7,7,7),('Wally_Random_FrSwimDownBloBubble',0,8,8,8,8,8),('Wally_Random_Minnows',0,7,7,7,7,7),('Wally_Random_PeekInSwimUp',0,5,5,5,5,5),('Wally_Random_PeekNduckBackDown',0,5,5,5,5,5),('Wally_Random_PeekNduckBackDown2',0,5,5,5,5,5),('Wally_Random_PerchCrossing',0,0,0,0,0,0),('Wally_Random_SnailCrossing',0,3,3,3,3,3),('Wally_Random_SwimAcrossL2R',0,4,4,4,4,4),('Wally_Random_SwimAcrossR2L',0,5,5,5,5,5),('Wally_Random_SwimTop2Bottom',0,1,1,1,1,1),('Wally_Random_SwimTop2BottomR',0,1,1,1,1,1),('Wally_StoryIntro_BubbleDream',0,10,10,10,10,10),('Wally_StoryOutro_BubbleDream',0,10,10,10,10,10),('Wally_Story_Dishes',19650,7,7,7,7,7),('Wally_Story_Faucet',10060,7,7,7,7,7),('Wally_Story_Shower',34440,7,7,7,7,7),('Wally_Story_Sprinkler',40530,7,7,7,7,7),('Wally_Story_Washer',0,7,7,7,7,7);
/*!40000 ALTER TABLE `time_series` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `timing`
--

DROP TABLE IF EXISTS `timing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timing` (
  `message_section` smallint(6) NOT NULL,
  `delay` smallint(6) NOT NULL,
  `interval` smallint(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timing`
--

LOCK TABLES `timing` WRITE;
/*!40000 ALTER TABLE `timing` DISABLE KEYS */;
INSERT INTO `timing` VALUES (10,60,30);
/*!40000 ALTER TABLE `timing` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2016-12-30  3:04:09