<?php

namespace App\Models;

class User extends Usuario
{
    // Bridge temporal: Laravel sigue apuntando a App\Models\User en config/auth.php,
    // pero la tabla real del dominio es usuarios y el modelo base es Usuario.
}
