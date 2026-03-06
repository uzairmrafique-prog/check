<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Setup\{Branch, Image, Operator, SocialLink, Vendor, Bookmark};

class User extends Authenticatable implements Auditable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;
    use \OwenIt\Auditing\Auditable;


    protected $table = 'users';
    protected $appends = ['text'];
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'mobile_no',
        'password',
        'email',
        'location',
        'lat',
        'lng',
        'email_verified',
        'email_otp',
        'sms_otp',
        'otp_expires_at',
        'is_active'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'pin_code',
        'remember_token',
        'sanctum_token',
    ];



    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin_code' => 'hashed',
        ];
    }

    public function getTextAttribute()
    {
        return $this->attributes['name'];
    }

    public function social_links()
    {
        return $this->morphMany(SocialLink::class, 'model');
    }
    public function images()
    {
        return $this->morphMany(Image::class, 'model');
    }


    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'id', 'id');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class, 'id', 'user_id');
    }


    public function favoriteBranches()
    {
        return $this->belongsToMany(Branch::class, 'branch_favorites');
    }

    public function updateLocationIfEmpty(array $data): void
    {
        $updates = [];

        if (is_null($this->location)) {
            $updates['location'] = $data['location'];
        }

        if (is_null($this->lat)) {
            $updates['lat'] = $data['lat'];
        }

        if (is_null($this->lng)) {
            $updates['lng'] = $data['lng'];
        }

        if ($updates) {
            $this->update($updates);
        }
    }
}
