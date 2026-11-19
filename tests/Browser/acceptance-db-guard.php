<?php

function acceptanceSchemaGrantIsLiteral(string $scope, string $database, bool $partialRevokes): bool
{
    $name = $partialRevokes ? $database : str_replace('_', '\\_', $database);

    return strcasecmp(str_replace('`', '', $scope), $name.'.*') === 0;
}
