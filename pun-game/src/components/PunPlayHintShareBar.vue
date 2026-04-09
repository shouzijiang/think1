<template>
  <view class="mid-action-bar">
    <view class="mid-action-inner">
      <button
        v-if="showHint"
        class="mid-act-btn mid-act-btn--hint"
        :class="{ 'mid-act-btn--cooldown': hintNoQuota }"
        :loading="hintLoading"
        :disabled="hintBtnDisabled"
        hover-class="mid-act-btn--hover"
        @click="emit('hint')"
      >
        <image
          v-if="!hintLoading"
          class="mid-act-ico-img"
          src="/static/hintLabel.png"
          mode="aspectFit"
        />
        <text class="mid-act-txt">{{ hintLabel }}</text>
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
          src="/static/share.png"
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
  /** 是否显示「答案」按钮（如共创无揭字接口时可关闭） */
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

const hintBtnDisabled = computed(
  () => props.hintLoading || hintNoQuota.value || props.hintLocked,
)

const hintLabel = computed(() => {
  if (hintNoQuota.value) return '答案（剩0）'
  const n = Number.isFinite(props.hintAnswerQuota) ? Math.max(0, Math.floor(props.hintAnswerQuota)) : 0
  return `答案（剩${n > 99 ? '99+' : n}）`
})

function onShareClick() {
  emit('help')
  // 仅标记「用户发起了分享动作」；真正奖励在页面 onShareAppMessage.success 里发放
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
  color: #5a6d7a;
  background: linear-gradient(180deg, #ffffff 0%, #f0faf9 100%);
  border: 1rpx solid rgba(169, 201, 238, 0.55);
  box-shadow: 0 4rpx 14rpx rgba(169, 201, 238, 0.12);
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
