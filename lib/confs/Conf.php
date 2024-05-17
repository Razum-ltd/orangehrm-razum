<?php

class Conf {

    private string $dbHost;
    private string $dbPort;
    private string $dbName;
    private string $dbUser;
    private string $dbPass;
    private string $googleCalendarId;
    private string $googleApplicationCredentials;

    public function __construct() {
        $this->dbHost = getenv('ORANGEHRM_DATABASE_HOST');
        $this->dbPort = '3306';
        $this->dbName = getenv('ORANGEHRM_DATABASE_NAME');
        $this->dbUser = getenv('ORANGEHRM_DATABASE_USER');
        $this->dbPass = getenv('ORANGEHRM_DATABASE_PASSWORD');
        $this->googleCalendarId = getenv('GOOGLE_CALENDAR_ID');
        $this->googleApplicationCredentials = getenv('GOOGLE_APPLICATION_CREDENTIALS');
    }

    public function getDbHost(): string {
        return $this->dbHost;
    }

    public function getDbPort(): string {
        return $this->dbPort;
    }

    public function getDbName(): string {
        return $this->dbName;
    }

    public function getDbUser(): string {
        return $this->dbUser;
    }

    public function getDbPass(): string {
        return $this->dbPass;
    }

    public function getGoogleCalendarId(): string {
        return $this->googleCalendarId;
    }

    public function getGoogleApplicationCredentials(): string {
        return $this->googleApplicationCredentials;
    }

}
