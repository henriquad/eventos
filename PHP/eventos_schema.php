<?php

function eventosTableExists($dbcon, $tableName)
{
    $tableNameSafe = mysqli_real_escape_string($dbcon, $tableName);
    $sql = "SHOW TABLES LIKE '" . $tableNameSafe . "'";
    $result = mysqli_query($dbcon, $sql);

    return $result && mysqli_num_rows($result) > 0;
}

function eventosColumnExists($dbcon, $tableName, $columnName)
{
    $tableNameSafe = str_replace('`', '``', $tableName);
    $columnNameSafe = mysqli_real_escape_string($dbcon, $columnName);
    $sql = "SHOW COLUMNS FROM `" . $tableNameSafe . "` LIKE '" . $columnNameSafe . "'";
    $result = mysqli_query($dbcon, $sql);

    return $result && mysqli_num_rows($result) > 0;
}

function eventosGarantirSchema($dbcon)
{
    $sqlCreate = "CREATE TABLE IF NOT EXISTS eventos (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        evento VARCHAR(100) NOT NULL,
        valorE DOUBLE NULL,
        grupo VARCHAR(50) NULL,
        DC VARCHAR(1) NULL,
        prorroga VARCHAR(3) NULL,
        diario VARCHAR(4) NULL,
        diaM VARCHAR(2) NULL,
        diaS VARCHAR(3) NULL,
        diaU VARCHAR(2) NULL,
        UPA VARCHAR(1) NULL,
        dataF DATE NULL,
        semN VARCHAR(1) NULL,
        semM VARCHAR(3) NULL,
        semD VARCHAR(3) NULL,
        diaR VARCHAR(2) NULL,
        mesR VARCHAR(3) NULL,
        ativo BOOLEAN NULL,
        diaHora DATETIME NOT NULL,
        INDEX idx_evento (evento),
        INDEX idx_grupo (grupo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    if (!mysqli_query($dbcon, $sqlCreate)) {
        die('Erro na criação da tabela: ' . mysqli_error($dbcon));
    }

    if (!eventosColumnExists($dbcon, 'eventos', 'apelido')) {
        if (!mysqli_query($dbcon, "ALTER TABLE eventos ADD COLUMN apelido VARCHAR(50) NOT NULL DEFAULT '' AFTER grupo")) {
            die('Erro ao criar coluna apelido: ' . mysqli_error($dbcon));
        }
    }

    if (!eventosColumnExists($dbcon, 'eventos', 'senha')) {
        if (!mysqli_query($dbcon, "ALTER TABLE eventos ADD COLUMN senha VARCHAR(255) NOT NULL DEFAULT '' AFTER apelido")) {
            die('Erro ao criar coluna senha: ' . mysqli_error($dbcon));
        }
    }
}
