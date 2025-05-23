<?php

namespace PhpMx\Datalayer\Driver;

use PhpMx\Datalayer;
use PhpMx\Datalayer\Query;
use PhpMx\Datalayer\Driver\Field\FIdx;
use PhpMx\Datalayer\Driver\Field\FTime;
use Error;

/**
 * @property int|null $id chave de identificação numerica do registro
 */
abstract class Record
{
    protected ?int $ID = null;
    protected array $FIELD = [];

    protected array $INITIAL = [];
    protected array $FIELD_REF_NAME = [];

    protected string $DATALAYER;
    protected string $TABLE;

    protected bool $DELETE = false;

    function __construct(array $scheme)
    {
        $this->FIELD['_created'] = new FTime(false, 0);
        $this->FIELD['_updated'] = new FTime(false, 0);

        $this->_arraySet($scheme);

        $this->ID = $scheme['id'] ?? null;
        $this->INITIAL = $this->_arrayInsert();

        if ($this->_checkInDb()) {
            $drvierClass = Datalayer::formatNameToDriverClass($this->DATALAYER);
            $tableClass = Datalayer::formatNameToMethod($this->TABLE);
            $drvierClass::${$tableClass}->__cacheSet($this->ID, $this);
        }
    }

    /** Retorna a chave de identificação numerica do registro */
    final function id(): ?int
    {
        return $this->ID;
    }

    /** Retorna a chave de identificação cifrada */
    final function idKey(): string
    {
        $drvierClass = Datalayer::formatNameToDriverClass($this->DATALAYER);
        $tableClass = Datalayer::formatNameToMethod($this->TABLE);

        return $drvierClass::${$tableClass}->idToIdkey($this->id);
    }

    /** Retorna o valor do esquema de um campo do registro */
    final function _schemeValue(string $field)
    {
        $name = isset($this->FIELD_REF_NAME[$field]) ? $this->FIELD_REF_NAME[$field] : $field;
        return method_exists($this, "get_$name") ? $this->{"get_$name"}() : $this->_array($field)[$field];
    }

    /** Retorna o esquema dos campos do registro tratados em forma de array */
    final function _scheme(array $fields): array
    {
        $scheme = [];

        foreach ($fields as $field)
            $scheme[$field] = $this->_schemeValue($field);

        return $scheme;
    }

    /** Retorna todo os campos e esquemas personalizados do registro tratados em forma de array */
    final function _schemeAll(array $fieldsRemove = []): array
    {
        $schemeFields = [
            'idKey',
            '_changed',
            ...array_keys($this->FIELD_REF_NAME)
        ];

        $schemeFields = array_flip($schemeFields);

        foreach (get_class_methods(static::class) as $class) {
            if (str_starts_with($class, 'get_')) {
                $fieldName = substr($class, 4);
                if (!isset($schemeFields[$fieldName]))
                    $schemeFields[$fieldName] = count($schemeFields);
            }
        }

        foreach ($fieldsRemove as $remove)
            if (isset($schemeFields[$remove]))
                unset($schemeFields[$remove]);


        $schemeFields = array_flip($schemeFields);
        $schemeFields = array_values($schemeFields);

        return $this->_scheme($schemeFields);
    }

    /** Retorna o momento em que o campo foi criado */
    final function _created(): int
    {
        return $this->FIELD['_created']->get();
    }

    /** Retorna o momento da ultima atualização do campo  */
    final function _updated(): int
    {
        return $this->FIELD['_updated']->get();
    }

    /** Retorna o momento da ultima mudança (create ou update) do campo  */
    final function _changed(): int
    {
        return $this->_updated() ? $this->_updated() : $this->_created();
    }

    /** Retorna o esquema de _changed */
    final function get__changed()
    {
        return $this->_changed();
    }

    /** Marca o registro como ativo */
    final function _makeActive(): static
    {
        $drvierClass = Datalayer::formatNameToDriverClass($this->DATALAYER);
        $tableClass = Datalayer::formatNameToMethod($this->TABLE);

        $drvierClass::${$tableClass}->active($this);
        return $this;
    }

    /** Retorna os campos do registro em forma de array */
    final function _array(...$fields)
    {
        if (empty($fields))
            $fields = ['id', 'idKey', ...array_keys($this->FIELD)];

        $scheme = [];

        foreach ($fields as $field) {
            if ($field == 'id') {
                $scheme[$field] = $this->id();
            } else if ($field == 'idKey') {
                $scheme[$field] = $this->idKey();
            } else {
                $name = isset($this->FIELD_REF_NAME[$field]) ? $this->FIELD_REF_NAME[$field] : $field;
                if (isset($this->FIELD[$name]))
                    $scheme[$field] = $this->FIELD[$name]->get();
            }
        }

        return $scheme;
    }

    /** Define os valores dos campos do registro com base em um array */
    final function _arraySet(mixed $scheme): static
    {
        if (is_array($scheme)) {
            foreach ($scheme as $name => $value) {
                $name = isset($this->FIELD_REF_NAME[$name]) ? $this->FIELD_REF_NAME[$name] : $name;

                if (isset($this->FIELD[$name]))
                    $this->FIELD[$name]->set($value);
            }
        }
        return $this;
    }

    /** Retorna o array dos campos da forma como são salvos no banco de dados */
    final function _arrayInsert(bool $returnId = false): array
    {
        $return = $returnId ? ['id' => $this->id()] : [];

        foreach ($this->FIELD_REF_NAME as $name => $ref)
            $return[$name] = $this->FIELD[$ref]->_insert();

        return $return;
    }

    /** Aplica um array de mudanças aos campos do registro */
    final function _arrayChange(array $changes): static
    {
        $array = $this->_array();
        applyChanges($array, $changes);
        $this->_arraySet($array);
        return $this;
    }

    /** Verifica se o registro existe no banco de dados */
    final function _checkInDb(): bool
    {
        return !is_null($this->id()) && $this->id() > 0;
    }

    /** Verifica se algum dos campos fornecidos foi alterado */
    final function _checkChange(...$fields): bool
    {
        if (empty($fields))
            return $this->INITIAL != $this->_arrayInsert();


        $fields = array_filter($fields, fn($v) => !str_starts_with($v, '_'));

        $flipNames = array_flip($this->FIELD_REF_NAME);

        $initial = $this->INITIAL;
        $current = $this->_arrayInsert();


        foreach ($fields as $field) {
            if (isset($flipNames[$field]))
                $field = $flipNames[$field];

            if (in_array($field, array_keys($initial)))
                if ($initial[$field] != $current[$field])
                    return true;
        }

        return false;
    }

    /** Verifica se o registro pode ser salvo no banco de dados */
    final function _checkSave(): bool
    {
        return !is_null($this->id()) && $this->id() >= 0;
    }

    /** Prepara o registro para ser excluido PERMANENTEMENTE no proximo _save */
    final function _delete(bool $delete): static
    {
        $this->DELETE = $delete;
        return $this;
    }

    /** Salva o registro no banco de dados */
    final function _save(bool $forceUpdate = false): static
    {
        if ($this->_checkSave())
            match (true) {
                $this->DELETE => $this->__runDelete(),
                $this->_checkInDb() => $this->__runUpdate($forceUpdate),
                default => $this->__runCreate()
            };

        return $this;
    }

    /** Executa o comando parar salvar os registros referenciados via IDX */
    final protected function __runSaveIdx()
    {
        foreach ($this->FIELD as &$field)
            if (is_class($field, FIdx::class) && $field->_checkLoad() && $field->_checkSave())
                if (!$field->id ||  $field->id != $this->ID || !is_class($field->_record(), $this::class))
                    $field->_save();
    }

    /** Executa o comando parar criar o registro */
    final protected function __runCreate()
    {
        $this->__runSaveIdx();
        $onCreate = $this->_onCreate() ?? null;
        if ($onCreate ?? true) {
            $this->FIELD['_created']->set(true);

            $this->ID = Query::insert($this->TABLE)
                ->values($this->_arrayInsert())
                ->run($this->DATALAYER);

            $drvierClass = Datalayer::formatNameToDriverClass($this->DATALAYER);
            $tableClass = Datalayer::formatNameToMethod($this->TABLE);

            $drvierClass::${$tableClass}->__cacheSet($this->ID, $this);

            if (is_callable($onCreate))
                $onCreate($this);
        }
    }

    /** Executa o comando parar atualizar o registro */
    final protected function __runUpdate(bool $forceUpdate)
    {
        $this->__runSaveIdx();
        if ($forceUpdate || $this->_checkChange()) {
            $onUpdate = $this->_onUpdate() ?? null;
            if ($onUpdate ?? true) {
                $dif = $this->_arrayInsert();

                foreach ($dif as $name => $value)
                    if ($value == $this->INITIAL[$name])
                        unset($dif[$name]);

                $dif['_updated'] = time();
                $this->FIELD['_updated']->set($dif['_updated']);

                Query::update($this->TABLE)
                    ->where('id', $this->ID)
                    ->values($dif)
                    ->run($this->DATALAYER);

                if (is_callable($onUpdate))
                    $onUpdate($this);
            }
        }
    }

    /** Executa o comando para deletar o registro do banco de dados */
    final protected function __runDelete()
    {
        $onDelete = $this->_onDelete() ?? null;
        if ($onDelete ?? true) {
            Query::delete($this->TABLE)
                ->where('id', $this->ID)
                ->run($this->DATALAYER);

            $oldId = $this->ID;
            $this->ID = null;

            $drvierClass = Datalayer::formatNameToDriverClass($this->DATALAYER);
            $tableClass = Datalayer::formatNameToMethod($this->TABLE);

            $drvierClass::${$tableClass}->__cacheRemove($oldId);

            if (is_callable($onDelete))
                $onDelete($this);
        }
    }

    final function __get($name)
    {
        if ($name == 'id') return $this->ID;

        if ($name == 'idKey') return $this->idKey();

        if (!isset($this->FIELD[$name]))
            throw new Error("Field [$name] not exists in [$this->TABLE]");

        return $this->FIELD[$name];
    }

    final function __call($name, $arguments)
    {
        if (!isset($this->FIELD[$name]))
            throw new Error("Field [$name] not exists in [$this->TABLE]");

        if (!count($arguments))
            return $this->FIELD[$name]->get();

        $this->FIELD[$name]->set(...$arguments);
        return $this;
    }

    protected function _onCreate() {}

    protected function _onUpdate() {}

    protected function _onDelete() {}
}
