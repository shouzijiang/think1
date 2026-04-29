/**
 * 玩法模式（gameTier）枚举
 *
 * 前后端统一使用以下四个值，后端 PunService::normalizeMode() 以此为准。
 * 禁止在业务代码里直接写字符串字面量，请 import 此文件使用。
 */
export const GAME_TIER_BEGINNER     = 'beginner'   // 入门（梗图填词）
export const GAME_TIER_MID          = 'mid'         // 中级（经典）
export const GAME_TIER_XHS          = 'xhs'         // 小红书专辑
export const GAME_TIER_BATTLE       = 'battle'      // 1V1 对战
