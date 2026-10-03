\set ON_ERROR_STOP on

CREATE ROLE dashboard WITH LOGIN PASSWORD 'dashboard_local';
CREATE DATABASE dashboard_monitor OWNER dashboard;
CREATE DATABASE dashboard_monitor_testing OWNER dashboard;
