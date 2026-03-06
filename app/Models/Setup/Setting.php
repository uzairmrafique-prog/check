<?php

namespace App\Models\Setup;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Setting extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    public function get($key)
    {
        $found = $this->db()
            ->where('key', $key)
            ->first();

        return optional($found)->value;
    }

    public function getMany($keys)
    {
        $output = [];

        foreach ($keys as $type => $key) {
            $output[$key] = $this->get($key);
        }

        return $output;
    }

    /**
     * Update the value of the provided key
     *
     * @param string $key
     * @param string $value
     * @return boolean
     */
    public function set($key, $value)
    {
        return $this->db()->where('key', $key)->update(['value' => $value]);
    }

    public function setMany($array)
    {
        foreach ($array as $key => $value) {
            $this->db()->where('key', $key)->update(['value' => $value]);
        }
    }

    /**
     * Update the value of key with null
     *
     * @param  string $key
     * @return boolean
     */
    public function forget($key)
    {
        return $this->set($key, null);
    }

    /**
     * Get new database instance
     *
     * @return DB
     */
    protected function db()
    {
        return DB::table('settings');
    }
}
