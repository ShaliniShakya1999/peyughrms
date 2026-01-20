<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Plan;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Mail;

class UserObserver
{
    public function creating(User $user): void
    {
        if (isSaas() && $user->type === 'company' && is_null($user->plan_id)) {
            $defaultPlan = Plan::getDefaultPlan();
            if ($defaultPlan) {
                $user->plan_id = $defaultPlan->id;
                $user->plan_is_active = 1;
            }
        }
    }

    public function created(User $user): void
    {
        // Referral Code Generate
        if (isSaas() && $user->type === 'company' && empty($user->referral_code)) {
            do {
                $code = rand(100000, 999999);
            } while (User::where('referral_code', $code)->exists());

            $user->referral_code = $code;
            $user->save();
        }

        // Default Settings Create
        if (isSaas() && $user->type === 'superadmin') {
            createDefaultSettings($user->id);
        } elseif ($user->type === 'company') {
            copySettingsFromSuperAdmin($user->id);
        }

        /**
         * ⭐ SEND EMAIL TO NEW USER ⭐
         */

        // Email template
        $template = EmailTemplate::where('name', 'User Created')->first();

        if (!$template) {
            return;
        }

        // ⭐ Correct translation fetch
        $lang = $template->lang('en');   // <---- yahi use karna hai

        if (!$lang) {
            return;
        }

        // Replace variables
        $subject = str_replace('{user_name}', $user->name, $lang->subject);

        $content = $lang->content;
        $content = str_replace('{user_name}', $user->name, $content);
        $content = str_replace('{user_email}', $user->email, $content);
        $content = str_replace('{user_password}', $user->plain_password ?? '******', $content);
        $content = str_replace('{user_type}', $user->type ?? 'User', $content);
        $content = str_replace('{app_url}', config('app.url'), $content);
        $content = str_replace('{app_name}', config('app.name'), $content);

        // Email send
        Mail::send([], [], function ($message) use ($user, $subject, $content, $template) {
            $message->to($user->email)
                ->subject($subject)
                ->from($template->from)
                ->html($content);
        });
    }
}
