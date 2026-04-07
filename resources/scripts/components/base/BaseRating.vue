<script setup lang="ts">
import { ref, computed, onBeforeMount } from 'vue'

interface RatingConfig {
  style?: Partial<RatingStyle>
  isIndicatorActive?: boolean
}

interface RatingStyle {
  fullStarColor: string
  emptyStarColor: string
  starWidth: number
  starHeight: number
}

interface StarData {
  raw: number
  percent: string
}

interface Props {
  config?: RatingConfig | null
  rating?: number
}

const props = withDefaults(defineProps<Props>(), {
  config: null,
  rating: 0,
})

const EMPTY_STAR = 0
const FULL_STAR = 1
const TOTAL_STARS = 5

const stars = ref<StarData[]>([])
const isIndicatorActive = ref<boolean>(false)
const style = ref<RatingStyle>({
  fullStarColor: '#F1C644',
  emptyStarColor: '#D4D4D4',
  starWidth: 20,
  starHeight: 20,
})

const getStarPoints = computed<string>(() => {
  const centerX = style.value.starWidth / 2
  const centerY = style.value.starHeight / 2
  const innerCircleArms = 5
  const innerRadius = style.value.starWidth / innerCircleArms
  const innerOuterRadiusRatio = 2.5
  const outerRadius = innerRadius * innerOuterRadiusRatio

  return calcStarPoints(centerX, centerY, innerCircleArms, innerRadius, outerRadius)
})

function calcStarPoints(
  centerX: number,
  centerY: number,
  innerCircleArms: number,
  innerRadius: number,
  outerRadius: number
): string {
  const angle = Math.PI / innerCircleArms
  const angleOffsetToCenterStar = 60
  const totalArms = innerCircleArms * 2
  let points = ''

  for (let i = 0; i < totalArms; i++) {
    const isEvenIndex = i % 2 === 0
    const r = isEvenIndex ? outerRadius : innerRadius
    const currX = centerX + Math.cos(i * angle + angleOffsetToCenterStar) * r
    const currY = centerY + Math.sin(i * angle + angleOffsetToCenterStar) * r
    points += currX + ',' + currY + ' '
  }

  return points
}

function calcStarFullness(starData: StarData): string {
  return starData.raw * 100 + '%'
}

function getFullFillColor(starData: StarData): string {
  return starData.raw !== EMPTY_STAR
    ? style.value.fullStarColor
    : style.value.emptyStarColor
}

function initStars(): void {
  for (let i = 0; i < TOTAL_STARS; i++) {
    stars.value.push({
      raw: EMPTY_STAR,
      percent: EMPTY_STAR + '%',
    })
  }
}

function setStars(): void {
  let fullStarsCounter = Math.floor(props.rating)

  for (let i = 0; i < stars.value.length; i++) {
    if (fullStarsCounter !== 0) {
      stars.value[i].raw = FULL_STAR
      stars.value[i].percent = calcStarFullness(stars.value[i])
      fullStarsCounter--
    } else {
      const surplus = Math.round((props.rating % 1) * 10) / 10
      const roundedOneDecimalPoint = Math.round(surplus * 10) / 10
      stars.value[i].raw = roundedOneDecimalPoint
      stars.value[i].percent = calcStarFullness(stars.value[i])
      return
    }
  }
}

function setConfigData(): void {
  if (props.config) {
    if (props.config.style?.fullStarColor) {
      style.value.fullStarColor = props.config.style.fullStarColor
    }
    if (props.config.style?.emptyStarColor) {
      style.value.emptyStarColor = props.config.style.emptyStarColor
    }
    if (props.config.style?.starWidth) {
      style.value.starWidth = props.config.style.starWidth
    }
    if (props.config.style?.starHeight) {
      style.value.starHeight = props.config.style.starHeight
    }
    if (props.config.isIndicatorActive) {
      isIndicatorActive.value = props.config.isIndicatorActive
    }
  }
}

onBeforeMount(() => {
  initStars()
  setStars()
  setConfigData()
})
</script>

<template>
  <div class="star-rating">
    <div
      v-for="(star, index) in stars"
      :key="index"
      :title="String(rating)"
      class="star-container"
    >
      <svg
        :style="[
          { fill: `url(#gradient${star.raw})` },
          { width: style.starWidth },
          { height: style.starHeight },
        ]"
        class="star-svg"
      >
        <polygon :points="getStarPoints" style="fill-rule: nonzero" />
        <defs>
          <linearGradient :id="`gradient${star.raw}`">
            <stop
              id="stop1"
              :offset="star.percent"
              :stop-color="getFullFillColor(star)"
              stop-opacity="1"
            />
            <stop
              id="stop2"
              :offset="star.percent"
              :stop-color="getFullFillColor(star)"
              stop-opacity="0"
            />
            <stop
              id="stop3"
              :offset="star.percent"
              :stop-color="style.emptyStarColor"
              stop-opacity="1"
            />
            <stop
              id="stop4"
              :stop-color="style.emptyStarColor"
              offset="100%"
              stop-opacity="1"
            />
          </linearGradient>
        </defs>
      </svg>
    </div>
    <div v-if="isIndicatorActive" class="indicator">{{ rating }}</div>
  </div>
</template>

<style scoped>
.star-rating {
  display: flex;
  align-items: center;
  .star-container {
    display: flex;
  }
  .star-container:not(:last-child) {
    margin-right: 5px;
  }
}
</style>
