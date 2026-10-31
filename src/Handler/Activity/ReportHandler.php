<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 分享战报（移植旧版 controllers/report.php:save）
 *
 * 客户端（miscpop.js）调用 save&id=&type=：先按类型取本服战报（arena / challenge / countrymine / mine 为信件战报 pm_report，
 * map 为推图首杀战报 mission_report，siege 为国战战报 country_city_report；god / gvg / worldarena 是跨服战报），取不到回 -1；
 * 再把战报存到 uc 服务器换分享链接（transfer_model->save_report）。
 * 依赖未移植：uc 服务器的战报分享（transfer_model->save_report，跨服 / uc），取到本服战报后一律按分享失败回 -2；
 * 跨服战报（god / gvg / worldarena）一律按取战报失败回 -1。
 * 不移植：c=report&m=send（客户端发到外部错误收集地址 error.zhanchengkeji.com，不是游戏服接口）。
 */
final class ReportHandler
{
    /**
     * 保存战报: c=report&m=save&id=&type=
     */
    public static function save(Context $ctx): array
    {
        $return = ['status' => 0];
        $id = (int)$ctx->input('id', 0);
        $type = (string)$ctx->input('type', '');
        if (!self::reportExists($type, $id)) {
            // 获取战报失败
            $return['status'] = -1;
            return $return;
        }
        // 依赖未移植：transfer_model->save_report（uc 服务器保存分享战报、返回 link）
        // 分享战报失败
        $return['status'] = -2;
        return $return;
    }

    /**
     * 本服战报是否存在（_get_report 的本服分支）
     */
    private static function reportExists(string $type, int $id): bool
    {
        $table = match ($type) {
            'arena', 'challenge', 'countrymine', 'mine' => 'pm_report',
            'map' => 'mission_report',
            'siege' => 'country_city_report',
            default => null,
        };
        if ($table === null || $id <= 0) {
            return false;
        }
        return DB::table($table)->where('id', $id)->whereNull('deleted_at')->value('id') !== null;
    }
}
