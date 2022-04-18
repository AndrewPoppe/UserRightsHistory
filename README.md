# UserRightsHistory

[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=AndrewPoppe_UserRightsHistory&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=AndrewPoppe_UserRightsHistory)
[![Vulnerabilities](https://sonarcloud.io/api/project_badges/measure?project=AndrewPoppe_UserRightsHistory&metric=vulnerabilities)](https://sonarcloud.io/summary/new_code?id=AndrewPoppe_UserRightsHistory)
## Overview
This REDCap External Module allows users to track user access, rights, and permissions in a project over time. 

For example, this image shows the state of the User Rights at one point in time:

![interface example](images/example_interface.png)

whereas this image shows the same project's User Rights several days earlier:

![interface example](images/example_interface2.png)



## How does it work?
The module runs a cron job every minute. When the module is enabled in a 
project, that project is added to the cron job. An initial snapshot of the User 
Rights is taken, and when changes are made to any aspect of the User Rights in 
that project, another timestamped snapshot is taken. This produces a 
point-in-time history with granularity to the minute. 

## Installation
The module may be installed from the REDCap Repo.

## Compatibility Table

This table represents informal, real-world use as opposed to systematic functional testing.
Essentially, this table will tell you whether we have confirmed that the module generally
installs and functions correctly given the combination of REDCap and PHP versions.
<table style="text-align:center;">
    <tr>
        <th></th>
        <th colspan="2">REDCap Version</th>
    </tr>
    <tr>
        <th>PHP version</th>
        <th>10.0.28</th>
        <th>12.1.2</th>
    </tr>
    <tr>
        <th>7.3.32</th>
        <td ><img src="lib/iconoir/check-circled-outline.png"></td>
        <td><img src="lib/iconoir/check-circled-outline.png"></td>
    </tr>
    <tr>
        <th>7.4.5</th>
        <td><img src="lib/iconoir/question-mark.png"></td>
        <td><img src="lib/iconoir/question-mark.png"></td>
    </tr>
    <tr>
        <th>8.1.3</th>
        <td><img src="lib/iconoir/warning-circled-outline.png"></td>
        <td><img src="lib/iconoir/warning-circled-outline.png"></td>
    </tr>
</table>

#### Key
|                    Symbol                    | Meaning                                                                    |
| :------------------------------------------: | :------------------------------------------------------------------------- |
|  ![](lib/iconoir/check-circled-outline.png)  | Module installs correctly and seems to function correctly                  |
| ![](lib/iconoir/warning-circled-outline.png) | Module installs correctly but either has not been tested or has minor bugs |
|       ![](lib/iconoir/prohibition.png)       | Module fails to install or has major bugs                                  |
|      ![](lib/iconoir/question-mark.png)      | No attempt has been made to assess the module                              |
