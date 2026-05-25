const CLOUD_TIMEOUT_MS = 3500
const cache = new Map()
// 注意：hunyuan-v3 / hy3-preview 在 CloudBase 里需要走 taiji 专属接口，
// 不能通过 wx.cloud.extend.AI 的通用 streamText 直接调用。
const PROVIDER_CANDIDATES = ['hunyuan-v3']
const MODEL_CANDIDATES = ['hy3-preview']
let cloudExplainDisabled = false

function normalizeText(v) {
  return String(v || '').replace(/\s+/g, ' ').trim()
}

function withTimeout(promise, timeoutMs) {
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => {
      reject(new Error('timeout'))
    }, timeoutMs)
    promise
      .then((res) => {
        clearTimeout(timer)
        resolve(res)
      })
      .catch((err) => {
        clearTimeout(timer)
        reject(err)
      })
  })
}

function isWeixinMiniProgram() {
  try {
    const p = (uni.getAppBaseInfo?.() ?? uni.getSystemInfoSync()).uniPlatform || ''
    return p === 'mp-weixin'
  } catch (_) {
    return false
  }
}

function shouldDisableCloudByError(err) {
  const msg = String(err?.message || err?.errMsg || err || '')
  return /api\.taiji\.woa\.com\/openapi\/v2\/chat\/completions|该模型需要使用|not found in definitions|unauthorized|\b401\b|invalid_request/i.test(msg)
}

function parseChunkPayload(event) {
  // 兼容三种形态：
  // 1) { data: "{...}" }
  // 2) { data: { ... } }
  // 3) 直接就是 { ... }
  const raw = event && Object.prototype.hasOwnProperty.call(event, 'data')
    ? event.data
    : event

  if (raw == null || raw === '[DONE]') return null
  if (typeof raw === 'object') return raw

  const text = String(raw || '')
  if (!text || text === '[DONE]') return null
  try {
    return JSON.parse(text)
  } catch (_) {
    return null
  }
}

function getChunkText(payload) {
  if (!payload || typeof payload !== 'object') return ''
  const delta = payload?.choices?.[0]?.delta?.content
  if (typeof delta === 'string' && delta) return delta
  const msg = payload?.choices?.[0]?.message?.content
  if (typeof msg === 'string' && msg) return msg
  return ''
}

async function generateByCloud({ answer, hint }, onChunk) {
  if (cloudExplainDisabled) return ''
  if (!isWeixinMiniProgram()) return ''
  if (typeof wx === 'undefined') return ''
  const createModel = wx?.cloud?.extend?.AI?.createModel
  if (typeof createModel !== 'function') return ''

  const safeAnswer = normalizeText(answer)
  const safeHint = normalizeText(hint)
  const prompt = [
    `要求：仅解读${safeAnswer}词语含义，结合${safeHint || ''}范畴作答。用脑洞趣味风格，解释通俗易懂，单句话表述，字数控制50字上下。`,
  ].filter(Boolean).join('')

  let lastErr = null
  for (const provider of PROVIDER_CANDIDATES) {
    for (const modelName of MODEL_CANDIDATES) {
      try {
        const model = createModel(provider)
        const res = await model.streamText({
          data: {
            model: modelName,
            messages: [
              {
                role: 'user',
                content: prompt,
              },
            ],
            temperature: 0.7,
          },
        })

        let output = ''
        for await (const event of res.eventStream) {
          const payload = parseChunkPayload(event)
          if (!payload) continue
          const chunkText = getChunkText(payload)
          if (chunkText) {
            output += String(chunkText)
            if (typeof onChunk === 'function') onChunk(output)
          }
        }
        const text = normalizeText(output)
        if (text) return text
      } catch (err) {
        if (shouldDisableCloudByError(err)) {
          cloudExplainDisabled = true
          return ''
        }
        lastErr = err
      }
    }
  }
  if (lastErr) {
    throw lastErr
  }
  return ''
}

/**
 * 通关趣味解读：优先云端 AI，失败时回落本地模板。
 * @param {{answer?: string, hint?: string, gameTier?: 'beginner'|'mid'|'xhs'}} payload
 * @returns {Promise<string>}
 */
export async function generatePassExplain(payload = {}, onChunk) {
  const answer = normalizeText(payload.answer)
  const hint = normalizeText(payload.hint)
  const gameTier = normalizeText(payload.gameTier)
  const cacheKey = `${gameTier}|${answer}|${hint}`
  if (cache.has(cacheKey)) return cache.get(cacheKey)

  try {
    const aiText = await withTimeout(generateByCloud({ answer, hint }, onChunk), CLOUD_TIMEOUT_MS)
    const finalText = normalizeText(aiText)
    cache.set(cacheKey, finalText)
    return finalText
  } catch (_) {
    cache.set(cacheKey, '')
    return ''
  }
}
