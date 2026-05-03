<?php

namespace models;

use Vatts\Database\Model;

class User extends Model
{



    // Lembre de colocar 'static' nessas 3 propriedades!
    protected static ?string $table = 'users';
    protected static array $hidden = ['password'];

    public array $view_map = [
        'Identidade' => [
            ['label' => 'E-mail', 'key' => 'email', 'type' => 'email', 'desc' => 'Endereço de e-mail do usuário.'],
            ['label' => 'Usuário', 'key' => 'name', 'type' => 'text', 'desc' => 'Nome de usuário utilizado para login no painel.'],
            ['label' => 'Primeiro Nome', 'key' => 'first_name', 'type' => 'text', 'desc' => ''],
            ['label' => 'Último Nome', 'key' => 'last_name', 'type' => 'text', 'desc' => ''],
        ],
        'Segurança' => [
            ['label' => 'Senha', 'key' => 'password', 'type' => 'password', 'desc' => 'Deixe em branco para manter a senha deste usuário. O usuário não receberá nenhuma notificação caso a senha seja alterada.'],
        ],
        'Permissões' => [
            // Para enums/selects, recomendo passar as 'options' assim, fica muito mais limpo para o front-end renderizar
            ['label' => 'Administrador', 'key' => 'role', 'type' => 'select', 'options' => ['user' => 'Não (Usuário Padrão)', 'admin' => 'Sim (Administrador)'], 'desc' => 'Selecione o cargo deste usuário. Definir como Sim concede acesso administrativo total.']
        ]
    ];

    public static array $schema = [
        'id'         => 'id', // O ORM entende e ignora
        'name'       => 'string',
        'first_name' => 'string',
        'last_name'  => 'string',
        'email'      => 'string',
        'password'   => 'string',
        'role'       => 'enum:user,admin',
        'timestamps' => 'timestamps'
    ];

    // Tipagem (Autocomplete pro DEV)
    public int $id;
    public string $name;
    public string $first_name;
    public string $last_name;
    public string $email;
    public string $password;
    public string $role;


    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * @return Server[]
     */
    public function getOthersServers(): array
    {
        return Server::where("ownerId", "!=", $this->id);
    }

    /**
     * @return Server[]
     */
    public function getServers(): array
    {
        return Server::where("ownerId", $this->id);
    }

}