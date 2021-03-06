<?php

namespace craftnet\helpers;

use craft\commerce\elements\Subscription;
use craft\elements\User;
use craftnet\controllers\id\DeveloperSupportController;
use yii\db\Expression;
use Zendesk\API\HttpClient;
use Zendesk\API\Utilities\Auth;

abstract class Zendesk
{
    /**
     * Returns a new Zendesk HTTP client.
     *
     * @return HttpClient
     */
    public static function client(): HttpClient
    {
        $client = new HttpClient('craftcms');
        $client->setAuth(Auth::BASIC, [
            'username' => getenv('ZENDESK_USERNAME'),
            'token' => getenv('ZENDESK_TOKEN'),
        ]);
        return $client;
    }

    /**
     * Returns the support plan tag that should be applied to a ticket, per the customer email.
     *
     * @param string $email
     * @return string 'basic', 'pro', or 'premium'
     */
    public static function plan(string $email): string
    {
        $user = User::find()
            ->andWhere(new Expression('lower([[email]]) = :email', [':email' => $email]))
            ->one();

        if ($user) {
            // Are we manually setting their support plan?
            $supportPlan = (string)$user->supportPlan;
            if (
                $supportPlan &&
                $supportPlan !== DeveloperSupportController::PLAN_BASIC &&
                (!$user->supportPlanExpiryDate || $user->supportPlanExpiryDate > new \DateTime())
            ) {
                return $user->supportPlan;
            }

            if (self::checkPlan($user->id, DeveloperSupportController::PLAN_PREMIUM)) {
                return DeveloperSupportController::PLAN_PREMIUM;
            }

            if (self::checkPlan($user->id, DeveloperSupportController::PLAN_PRO)) {
                return DeveloperSupportController::PLAN_PRO;
            }
        }

        return DeveloperSupportController::PLAN_BASIC;
    }

    /**
     * Returns whether a user ID belongs to a support plan.
     *
     * @param int $userId
     * @param string $plan
     * @return bool
     */
    private static function checkPlan(int $userId, string $plan): bool
    {
        return Subscription::find()
            ->plan($plan)
            ->userId($userId)
            ->isExpired(false)
            ->exists();
    }
}
