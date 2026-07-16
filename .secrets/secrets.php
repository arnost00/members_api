<?php

class Secrets {
    // password_hash(..., PASSWORD_DEFAULT);
    public static string $logging_password_hash;

    // generate random string
    public static string $cron_secret_key;
}
