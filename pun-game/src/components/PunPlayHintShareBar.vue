<template>
  <view class="mid-action-bar">
    <view class="mid-action-inner">
      <button
        v-if="showHint"
        class="mid-act-btn mid-act-btn--hint"
        :class="{
          'mid-act-btn--cooldown': hintNoQuota,
          'mid-act-btn--hint-badged': showHintVideoBadge,
        }"
        :loading="hintLoading"
        :disabled="hintBtnDisabled"
        hover-class="mid-act-btn--hover"
        @click="emit('hint')"
      >
        <image
          v-if="!hintLoading"
          class="mid-act-ico-img"
          src="https://sofun.online/static/mini/hintLabel.png"
          mode="aspectFit"
        />
        <text class="mid-act-txt">{{ hintLabel }}</text>
        <!-- #ifdef MP-WEIXIN -->
        <view v-if="hintNoQuota" class="hint-video-tag" aria-hidden="true">
          <text class="hint-video-tag__play">▶</text>
        </view>
        <!-- #endif -->
      </button>
      <button
        v-if="showShare"
        class="mid-act-btn mid-act-btn--share"
        open-type="share"
        hover-class="mid-act-btn--hover"
        @click="onShareClick"
      >
        <image
          class="mid-act-ico-img"
          src="https://sofun.online/static/mini/share.png"
          mode="aspectFit"
        />
        <text class="mid-act-txt">分享+1次</text>
      </button>
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  /** 是否显示「答案」揭字按钮 */
  showHint: { type: Boolean, default: true },
  /** 是否显示「求助」分享按钮（对战页可关闭） */
  showShare: { type: Boolean, default: true },
  hintLoading: { type: Boolean, default: false },
  /** 揭字剩余次数（与后端 pun_user_hint_quota 一致；每点一次答案成功揭一步扣 1） */
  hintAnswerQuota: { type: Number, default: 0 },
  /** 额外禁用「答案」（如题目加载中） */
  hintLocked: { type: Boolean, default: false },
})

const emit = defineEmits(['hint', 'help', 'share-intent'])

const hintNoQuota = computed(() => props.hintAnswerQuota <= 0)

/** 微信小程序且无次数：角标在按钮内，整钮可点走激励视频；其它端无次数仍禁用 */
const isMpWeixin = computed(() => {
  try {
    return uni.getSystemInfoSync().uniPlatform === 'mp-weixin'
  } catch {
    return false
  }
})

const showHintVideoBadge = computed(() => isMpWeixin.value && hintNoQuota.value)

const hintBtnDisabled = computed(() => {
  const busy = props.hintLoading || props.hintLocked
  if (isMpWeixin.value && hintNoQuota.value) {
    return busy
  }
  return busy || hintNoQuota.value
})

const hintLabel = computed(() => {
  if (hintNoQuota.value) return '答案（剩0）'
  const n = Number.isFinite(props.hintAnswerQuota) ? Math.max(0, Math.floor(props.hintAnswerQuota)) : 0
  return `答案（剩${n > 99 ? '99+' : n}）`
})

function onShareClick() {
  emit('help')
  // 与页面 onShareAppMessage 合并去重后请求 share-reward（含右上角菜单转发）
  emit('share-intent')
}
</script>

<style lang="scss" scoped>
.mid-action-bar {
  display: flex;
  flex-direction: row;
  justify-content: center;
  padding: 16rpx 18rpx 20rpx;
  background: rgba(250, 252, 255, 0.92);
}

.mid-action-inner {
  display: flex;
  flex-direction: row;
  align-items: stretch;
  gap: 18rpx;
  width: 100%;
  max-width: 580rpx;
}

.mid-act-btn {
  flex: 1;
  min-width: 0;
  margin: 0;
  min-height: 76rpx;
  padding: 0 18rpx;
  border: none;
  border-radius: 38rpx;
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
  gap: 8rpx;
  box-sizing: border-box;
  line-height: 1.2;
}

.mid-act-btn::after {
  border: none;
}

.mid-act-ico-img {
  width: 40rpx;
  height: 40rpx;
  flex-shrink: 0;
}

.mid-act-txt {
  font-size: 28rpx;
  font-weight: 600;
  letter-spacing: 0.02em;
}

.mid-act-btn--hint {
  position: relative;
  color: #5a6d7a;
  background: linear-gradient(180deg, #ffffff 0%, #f0faf9 100%);
  border: 1rpx solid rgba(169, 201, 238, 0.55);
  box-shadow: 0 4rpx 14rpx rgba(169, 201, 238, 0.12);
}

/** 为按钮内右上角小视频标留出空间，避免压住主文案 */
.mid-act-btn--hint-badged {
  padding-right: 72rpx;
}

/* 微信小程序：嵌在「答案」按钮内右上角，点击整钮走激励视频 */
.hint-video-tag {
  position: absolute;
  right: 10rpx;
  top: 50%;
  transform: translateY(-50%);
  width: 44rpx;
  height: 44rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10rpx;
  background: linear-gradient(160deg, #fff7ed 0%, #ffedd5 100%);
  border: 1rpx solid rgba(251, 146, 60, 0.45);
  box-shadow: 0 2rpx 8rpx rgba(251, 146, 60, 0.18);
  pointer-events: none;
}

.hint-video-tag__play {
  font-size: 20rpx;
  line-height: 1;
  color: #ea580c;
  font-weight: 900;
  margin-left: 2rpx;
}

.mid-act-btn--share {
  color: #5a6d7a;
  background: linear-gradient(180deg, #fbfdff 0%, #eaf6f9 100%);
  border: 1rpx solid rgba(169, 201, 238, 0.55);
  box-shadow: 0 4rpx 14rpx rgba(169, 201, 238, 0.12);
  .mid-act-ico-img {
    width: 38rpx;
    height: 38rpx;
  }
}

.mid-act-btn--hover {
  opacity: 0.9;
}

.mid-act-btn--cooldown {
  opacity: 0.65;
}

.mid-act-btn--cooldown .mid-act-txt {
  font-size: 28rpx;
  font-weight: 600;
  letter-spacing: -0.02em;
}
</style>
