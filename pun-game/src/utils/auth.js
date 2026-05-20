// 认证工具类
import { api } from './api'

// 登录状态锁，防止并发调用
let isLoggingIn = false
let loginPromise = null
const LAST_LOGIN_TOUCH_DATE_KEY = 'pun_last_login_touch_date'

function getTodayKey() {
  const now = new Date()
  const y = now.getFullYear()
  const m = String(now.getMonth() + 1).padStart(2, '0')
  const d = String(now.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function touchLastLoginAtIfNeeded() {
  try {
    const today = getTodayKey()
    const touchedDate = uni.getStorageSync(LAST_LOGIN_TOUCH_DATE_KEY)
    if (touchedDate === today) return
    api
      .touchLogin()
      .then(() => {
        uni.setStorageSync(LAST_LOGIN_TOUCH_DATE_KEY, today)
      })
      .catch(() => {})
  } catch {}
}

/**
 * 传给后端（如 auth 微信或抖音 login）的会话类型（与 JWT、users.mp_platform 等业务字段对应）。
 * 抖音端返回 douyin，微信端返回 weixin。
 */
function getBackendLoginProvider() {
  // #ifdef MP-WEIXIN
  return 'weixin'
  // #endif
  // #ifdef MP-TOUTIAO
  return 'douyin'
  // #endif
  return 'weixin'
}

/**
 * uni.login 的 provider，与 getBackendLoginProvider 不同：
 * 抖音/头条小程序在 uni-app 里必须使用 toutiao，传 douyin 会导致取码失败，本地无法写入 userInfo。
 */
function getUniLoginProvider() {
  // #ifdef MP-WEIXIN
  return 'weixin'
  // #endif
  // #ifdef MP-TOUTIAO
  return 'toutiao'
  // #endif
  return 'weixin'
}

// 检查登录状态（须同时具备 token 与有效 user_id，避免仅有脏 token 跳过登录导致页面无 userInfo）
export function checkLogin() {
  const token = uni.getStorageSync('token')
  const userInfo = uni.getStorageSync('userInfo')
  if (!token || !userInfo) return false
  const uid = userInfo.user_id
  const n = Number(uid)
  return n > 0
}

// 小程序统一登录（微信/抖音共用）
export async function miniProgramLogin(forceRefresh = false) {
  // 如果正在登录中，返回同一个 Promise（除非强制刷新）
  if (isLoggingIn && loginPromise && !forceRefresh) {
    console.log('登录进行中，等待现有登录完成...')
    return loginPromise
  }

  // 检查是否已登录（除非强制刷新）
  if (checkLogin() && !forceRefresh) {
    touchLastLoginAtIfNeeded()
    return Promise.resolve(uni.getStorageSync('userInfo'))
  }

  // 设置登录锁
  isLoggingIn = true
  const backendProvider = getBackendLoginProvider()
  const sdkProvider = getUniLoginProvider()

  loginPromise = new Promise((resolve, reject) => {
    uni.login({
      provider: sdkProvider,
      success: async (loginRes) => {
        if (loginRes.code) {
          try {
            // 调用后端统一平台登录接口，传递 code（与 sdkProvider 解耦，仍用 weixin/douyin 区分接口）
            const result = await api.miniProgramLogin(backendProvider, loginRes.code)

            // 保存 token 和用户信息
            uni.setStorageSync('token', result.token)
            const stored = {
              user_id: result.user_id,
              openid: result.openid,
              nickname: result.nickname,
              avatar: result.avatar,
            }
            uni.setStorageSync('userInfo', stored)
            try {
              uni.setStorageSync(LAST_LOGIN_TOUCH_DATE_KEY, getTodayKey())
            } catch {}

            // 上报买量渠道（首次登录后一次性上报）
            try {
              const channel = uni.getStorageSync('pun_channel')
              if (channel) {
                api.reportChannel(channel).catch(() => {})
                uni.removeStorageSync('pun_channel')
              }
            } catch {}

            // 清除登录锁
            isLoggingIn = false
            loginPromise = null
            resolve(stored)
          } catch (error) {
            console.error(`${backendProvider} 登录失败:`, error)
            // 清除登录锁，允许重试
            isLoggingIn = false
            loginPromise = null

            // 如果是 code 相关的错误，可以提示用户
            if (error.message && (error.message.includes('code') || error.message.includes('40163'))) {
              console.warn('Code 已被使用或过期，需要重新获取')
            }

            reject(error)
          }
        } else {
          // 清除登录锁
          isLoggingIn = false
          loginPromise = null
          reject(new Error('获取登录凭证失败'))
        }
      },
      fail: (err) => {
        console.error(`${sdkProvider} uni.login 失败:`, err)
        // 清除登录锁
        isLoggingIn = false
        loginPromise = null
        reject(new Error(`${sdkProvider} 登录失败: ` + (err.errMsg || '未知错误')))
      },
    })
  })

  return loginPromise
}

// 强制重新登录（清除缓存并重新获取 code）
export async function forceLogin() {
  // 清除登录状态
  uni.removeStorageSync('token')
  uni.removeStorageSync('userInfo')
  // 强制刷新登录
  return miniProgramLogin(true)
}

// 兼容旧调用：保留原函数名，内部复用统一登录逻辑
export async function wechatLogin(forceRefresh = false) {
  return miniProgramLogin(forceRefresh)
}

// 退出登录
export function logout() {
  uni.removeStorageSync('token')
  uni.removeStorageSync('userInfo')
  uni.switchTab({
    url: '/pages/index/index',
  })
}

// 获取用户信息（兼容历史缓存字段）
export function getUserInfo() {
  const info = uni.getStorageSync('userInfo') || null
  if (!info || typeof info !== 'object') return info
  if (info.user_id == null && info.userId != null) {
    return { ...info, user_id: info.userId }
  }
  return info
}

// 处理登录过期（401错误）
export function handleLoginExpired() {
  // 清除登录信息
  uni.removeStorageSync('token')
  uni.removeStorageSync('userInfo')

  // 显示提示并重新加载小程序
  uni.showModal({
    title: '登录过期',
    content: '您的登录已过期，请重新登录',
    showCancel: false,
    confirmText: '确定',
    success: (res) => {
      if (res.confirm) {
        // 重新加载小程序
        uni.switchTab({
          url: '/pages/index/index',
        })
      }
    },
  })
}
