// API 请求工具类
import { API_BASE_URL } from '../config'
import { forceLogin, handleLoginExpired } from './auth'

const BASE_URL = API_BASE_URL

// 构建查询字符串（兼容微信小程序，不使用 URLSearchParams）
function buildQueryString(params) {
  const queryParts = []
  for (const key in params) {
    if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
      queryParts.push(`${encodeURIComponent(key)}=${encodeURIComponent(params[key])}`)
    }
  }
  return queryParts.length > 0 ? '?' + queryParts.join('&') : ''
}

// 获取 token
function getToken() {
  return uni.getStorageSync('token') || ''
}

// 使用 HTTP 的请求方法
function requestWithHttp(options) {
  return new Promise((resolve, reject) => {
    const header = {
      'Content-Type': 'application/json',
      ...options.header,
    }

    const token = getToken()
    if (!options.skipAuth && token) {
      header['Authorization'] = `Bearer ${token}`
    }

    const requestUrl = BASE_URL + options.url
    // console.log('发起 HTTP 请求:', requestUrl, options.method || 'GET', options.data)

    uni.request({
      url: requestUrl,
      method: options.method || 'GET',
      data: options.data || {},
      header,
      timeout: options.timeout || 15000,
      success: (res) => {
        if (res.statusCode === 200) {
          // console.log('请求响应:', res.statusCode, res.data)
          if (res.data.code === 200) {
            resolve(res.data.data)
          } else if (res.data.code === 401) {
            // token 失效：先尝试“强制重新登录 + 重试原请求（最多一次）”
            // 避免每次401都让用户手动再点一次
            if (options && options._authRetried) {
              handleLoginExpired()
              reject(new Error(res.data.message || '登录已过期，请重新登录'))
              return
            }

            forceLogin()
              .then(() => {
                const retryOptions = { ...(options || {}), _authRetried: true }
                requestWithHttp(retryOptions).then(resolve).catch(reject)
              })
              .catch(() => {
                handleLoginExpired()
                reject(new Error(res.data.message || '登录已过期，请重新登录'))
              })
          } else {
            reject(new Error(res.data.message || '请求失败'))
          }
        } else {
          const errorMsg = `网络请求失败 (状态码: ${res.statusCode})，请检查：\n1. 后端服务是否正常运行\n2. 请求地址是否正确: ${requestUrl}\n3. 小程序后台是否配置了合法域名`
          console.error(errorMsg)
          reject(new Error(errorMsg))
        }
      },
      fail: (err) => {
        console.error('请求失败:', err)
        let errorMsg = '网络请求失败'
        if (err.errMsg) {
          if (err.errMsg.includes('timeout') || err.errMsg.includes('超时')) {
            errorMsg = '网络请求超时，请检查网络后重试'
          } else if (err.errMsg.includes('fail')) {
            errorMsg = `网络请求失败，可能的原因：\n1. 后端服务未启动或无法访问 (${BASE_URL})\n2. 请求地址错误: ${requestUrl}\n3. 微信开发者工具 -> 设置 -> 项目设置 -> 是否勾选"不校验合法域名"\n4. 网络连接问题\n\n错误详情: ${err.errMsg}`
          } else {
            errorMsg = `网络请求失败: ${err.errMsg}`
          }
        } else if (err instanceof Error) {
          errorMsg = err.message
        } else {
          errorMsg = String(err)
        }
        reject(new Error(errorMsg))
      },
    })
  })
}

function request(options) {
  return requestWithHttp(options)
}

// API 接口
export const api = {
  // 微信登录（不需要 token）
  wechatLogin(code) {
    return request({
      url: '/auth/wechat/login',
      method: 'POST',
      data: { code },
      skipAuth: true,
    })
  },

  // 抖音登录（不需要 token）
  douyinLogin(code) {
    return request({
      url: '/auth/douyin/login',
      method: 'POST',
      data: { code },
      skipAuth: true,
    })
  },

  /**
   * 小程序登录（按平台选择后端接口）
   * @param {'weixin'|'douyin'} provider
   * @param {string} code
   */
  miniProgramLogin(provider, code) {
    if (provider === 'douyin') {
      return this.douyinLogin(code)
    }
    return this.wechatLogin(code)
  },

  /** 排行榜：page 从 1 开始 */
  getRankList(params = {}) {
    return request({
      url: `/pun/rank/list${buildQueryString(params)}`,
      method: 'GET',
    })
  },

  /** 1V1 全局对战排行榜：胜/负场次（无需登录） */
  getBattleRankList(params = {}) {
    return request({
      url: `/pun/battle/rank${buildQueryString(params)}`,
      method: 'GET',
      skipAuth: true,
    })
  },

  /** 首页「本期更新」：无需登录；无数据时返回 null */
  getChangelogLatest() {
    return request({
      url: '/pun/changelog/latest',
      method: 'GET',
      skipAuth: true,
    })
  },

  /** 首页统计：进度表人数与累计通关次数（初+中级 JSON 长度之和），无需登录 */
  getHomeStats() {
    return request({
      url: '/pun/stats/home',
      method: 'GET',
      skipAuth: true,
    })
  },

  /**
   * 提交答案
   */
  /**
   * @param {number} level
   * @param {string[]} userAnswer
   * @param {Record<string, unknown>} [extra] 例如 { gameTier: 'mid' } 供后端区分题库
   */
  submitAnswer(level, userAnswer, extra = {}) {
    return request({
      url: '/pun/answer/submit',
      method: 'POST',
      data: { level, userAnswer, ...extra },
    })
  },

  /**
   * 分步揭字提示（每次多揭示一个字，其余为 X）
   * @param {Object} data - { level, gameTier: 'mid'|'battle'|'beginner', roomId?, questionIndex? }
   */
  revealHint(data) {
    return request({
      url: '/pun/level/reveal-hint',
      method: 'POST',
      data: data || {},
    })
  },

  /**
   * 跳关（扣除 2 次查看答案次数，跳关标记 24 小时）
   * @param {{level:number,gameTier?:'beginner'|'mid'|'xhs'}} data
   */
  skipLevel(data) {
    return request({
      url: '/pun/level/skip',
      method: 'POST',
      data: data || {},
    })
  },

  /**
   * 统一领取接口：按 type 领取次数
   * @param {{type: 'share'|'reward_video'|'daily_noon_hint_5'|'daily_watch_ad_hint_1'|'daily_battle_3_hint_3'|'permanent_set_avatar'|'permanent_set_nickname'|'permanent_my_mini_program_hint_3', add?: number, subscribeStatus?: 'accept'|'reject', templateId?: string, launchScene?: string|number}} payload
   */
  claimReward(payload) {
    return request({
      url: '/pun/reward/claim',
      method: 'POST',
      data: payload || {},
    })
  },

  /**
   * 当前用户关卡进度（历史答题情况）
   * 响应 data 含 hintAnswerQuota（剩余）、hintAnswerTotalUsed（累计消耗，需库表 total_used）
   */
  getLevelProgress(params = {}) {
    return request({ url: `/pun/level/progress${buildQueryString(params)}`, method: 'GET' })
  },

  /**
   * 提交意见反馈
   * @param {Object} data - { type?, content, contact? }
   */
  submitFeedback(data) {
    return request({
      url: '/pun/feedback/submit',
      method: 'POST',
      data,
    })
  },

  /**
   * 上传用户头像到 COS，返回 { url } 
   * @param {string} filePath 本地临时文件路径（chooseAvatar 返回的 avatarUrl）
   */
  uploadAvatar(filePath) {
    return new Promise((resolve, reject) => {
      const token = uni.getStorageSync('token') || ''
      uni.uploadFile({
        url: BASE_URL + '/upload/avatar',
        filePath,
        name: 'file',
        header: token ? { Authorization: 'Bearer ' + token } : {},
        success(res) {
          try {
            const json = typeof res.data === 'string' ? JSON.parse(res.data) : res.data
            if (json && json.code === 200 && json.data && json.data.url) {
              resolve(json.data)
            } else {
              reject(new Error((json && json.message) || '上传失败'))
            }
          } catch {
            reject(new Error('响应解析失败'))
          }
        },
        fail(err) {
          reject(new Error((err && err.errMsg) || '上传失败'))
        },
      })
    })
  },

  /**
   * 更新用户信息（头像/昵称），需登录
   * @param {Object} data - { nickname?, avatar? } avatar 为 COS URL
   */
  updateUserInfo(data) {
    return request({
      url: '/auth/user/update',
      method: 'POST',
      data,
    })
  },

  /**
   * 论坛：获取帖子列表（分页）
   * @param {Object} params - { page?: number, page_size?: number }
   */
  getForumList(params = {}) {
    return request({
      url: `/pun/forum/list${buildQueryString(params)}`,
      method: 'GET',
    })
  },

  /**
   * 论坛：发布新帖子
   * @param {Object} data - { title?: string, content: string }
   */
  createForumTopic(data) {
    return request({
      url: '/pun/forum/topic/create',
      method: 'POST',
      data,
    })
  },

  /**
   * 论坛：获取帖子详情及回复
   * @param {Object} params - { id: number, page?: number, page_size?: number }
   */
  getForumDetail(params = {}) {
    return request({
      url: `/pun/forum/detail${buildQueryString(params)}`,
      method: 'GET',
    })
  },

  /**
   * 论坛：回复帖子/回复评论
   * @param {Object} data - { topic_id: number, content: string, reply_to_id?: number }
   */
  replyForum(data) {
    return request({
      url: '/pun/forum/reply/create',
      method: 'POST',
      data,
    })
  },

  /**
   * 1V1：创建对战房间
   */
  createBattleRoom(questionBank = 'xhs') {
    return request({
      url: '/pun/battle/create',
      method: 'POST',
      data: { questionBank }
    })
  },

  /**
   * 1V1：更新房间题库
   */
  updateBattleRoomBank(roomId, questionBank) {
    return request({
      url: '/pun/battle/update-bank',
      method: 'POST',
      data: { roomId, questionBank }
    })
  },

  /**
   * 保存订阅消息状态
   */
  saveSubscribe(templateId, subscribeStatus) {
    return request({
      url: '/subscribe/save',
      method: 'POST',
      data: { template_id: templateId, subscribe_status: subscribeStatus }
    })
  },

  /**
   * 1V1：获取历史对战记录
   * @param {Object} params - { page?: number, page_size?: number }
   */
  getBattleHistory(params = {}) {
    return request({
      url: `/pun/battle/history${buildQueryString(params)}`,
      method: 'GET'
    })
  },
  /**
   * 信箱列表
   * @param {Object} params
   * @param {number} [params.page]
   * @param {number} [params.page_size]
   */
  getMailList(params = {}) {
    return request({
      url: `/pun/mail/list${buildQueryString(params)}`,
      method: 'GET'
    })
  },

  /**
   * 信箱详情（会在详情页读取并自动标记已读）
   * @param {Object} params
   * @param {number} params.id
   */
  getMailDetail(params = {}) {
    return request({
      url: `/pun/mail/detail${buildQueryString(params)}`,
      method: 'GET'
    })
  },

  /**
   * 上报买量渠道（登录成功后调用一次）
   * @param {string} channel 渠道标识，如 toutiao_0501
   */
  reportChannel(channel) {
    return request({
      url: '/channel/report',
      method: 'POST',
      data: { channel },
    })
  },

  /** 主播同玩：生成专属邀请小程序码（返回 { channel, qrBase64 }） */
  getStreamerQrCode() {
    return request({ url: '/pun/streamer/qrcode', method: 'GET' })
  },

  /** 主播同玩：获取邀请数据（受邀用户 + 行为统计） */
  getStreamerStats() {
    return request({ url: '/pun/streamer/stats', method: 'GET' })
  },
}
