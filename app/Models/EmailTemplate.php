<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    protected $fillable = [
        'name',
        'from',
        'user_id',
    ];

    /**
     * Relation: All translated versions of this template
     */
    public function emailTemplateLangs(): HasMany
    {
        return $this->hasMany(EmailTemplateLang::class, 'parent_id');
    }

    /**
     * Relation: Templates linked to users
     */
    public function userEmailTemplates(): HasMany
    {
        return $this->hasMany(UserEmailTemplate::class, 'template_id');
    }

    /**
     * ⭐ Helper: Get translation by language code (default: EN)
     * Example: $template->lang('en')
     */
    public function lang($code = 'en')
    {
        return $this->emailTemplateLangs()->where('lang', $code)->first();
    }
}