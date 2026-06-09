import { useEffect, useRef, useState } from 'react'
import { TiltCard } from '@/components/ui/tilt-card'
import type { Course } from '../data/courses'

interface WorksProps {
  courses: Course[]
  onSelectCourse: (id: string) => void
}

export default function Works({ courses, onSelectCourse }: WorksProps) {
  const sectionRef = useRef<HTMLElement>(null)
  const gridRef = useRef<HTMLDivElement>(null)
  const [isMobile, setIsMobile] = useState(false)

  useEffect(() => {
    const media = window.matchMedia('(max-width: 700px)')
    const sync = () => setIsMobile(media.matches)
    sync()
    media.addEventListener('change', sync)

    return () => media.removeEventListener('change', sync)
  }, [])

  useEffect(() => {
    const section = sectionRef.current
    if (!section) return
    if (window.matchMedia('(max-width: 700px)').matches) return

    let ctx: { revert: () => void } | undefined
    let disposed = false

    void (async () => {
      const [{ default: gsap }, { ScrollTrigger }] = await Promise.all([
        import('gsap'),
        import('gsap/ScrollTrigger'),
      ])
      if (disposed) return

      gsap.registerPlugin(ScrollTrigger)
      ctx = gsap.context(() => {
      gsap.from('.work-item', {
        opacity: 0,
        duration: 0.9,
        stagger: 0.12,
        ease: 'power3.out',
        scrollTrigger: {
          trigger: section,
          start: 'top 70%',
          once: true,
        },
      })
      }, section)
    })()

    return () => {
      disposed = true
      ctx?.revert()
    }
  }, [])

  return (
    <section
      id="courses"
      ref={sectionRef}
      style={{
        backgroundColor: '#f4f4f5',
        padding: '120px clamp(20px, 4vw, 60px)',
      }}
    >
      <div style={{ maxWidth: '1560px', margin: '0 auto' }}>
        <div
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'baseline',
            marginBottom: '60px',
            borderBottom: '1px solid #1a1a1a',
            paddingBottom: '20px',
          }}
        >
          <h2
            style={{
              fontSize: 'clamp(36px, 5vw, 64px)',
              fontWeight: 400,
              letterSpacing: '-0.03em',
              lineHeight: 1,
              color: '#000000',
            }}
          >
            Популярные курсы
          </h2>
          <span
            className="works-section-aside"
            style={{
              fontSize: '12px',
              letterSpacing: '0.18em',
              color: '#666666',
              textTransform: 'uppercase',
            }}
          >
            Выбери направление
          </span>
        </div>

        <div
          ref={gridRef}
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 520px), 1fr))',
            gap: '16px',
          }}
        >
          {courses.map((course, index) => (
            <CourseCard
              key={course.id}
              course={course}
              index={index}
              isMobile={isMobile}
              onClick={() => onSelectCourse(course.id)}
            />
          ))}
        </div>
      </div>
    </section>
  )
}

function CourseCard({
  course,
  index,
  onClick,
  isMobile,
}: {
  course: Course
  index: number
  onClick: () => void
  isMobile: boolean
}) {
  const content = (
    <button
      onClick={onClick}
      className="course-card__button"
      style={{
        border: '1px solid rgba(0,0,0,0.18)',
        backgroundColor: '#ffffff',
        padding: 0,
        cursor: 'pointer',
        textAlign: 'left',
        display: 'block',
        fontFamily: 'inherit',
        overflow: 'hidden',
        width: '100%',
      }}
    >
      <div
        style={{
          position: 'relative',
          width: '100%',
          paddingBottom: '56.25%',
          overflow: 'hidden',
          backgroundColor: '#e5e5e5',
        }}
      >
        <img
          src={course.img}
          alt={course.title}
          loading={isMobile ? 'eager' : 'lazy'}
          decoding="async"
          fetchPriority={isMobile && index < 3 ? 'high' : 'auto'}
          style={{
            position: 'absolute',
            top: 0,
            left: 0,
            width: '100%',
            height: '100%',
            display: 'block',
            objectFit: 'cover',
          }}
        />
        <div className="course-card__shade" />
        <div className="course-card__meta">
          <span>{course.lessons}</span>
          <span>{course.level}</span>
        </div>
      </div>
      <div
        className="course-card__body"
        style={{
          padding: '22px 24px 24px',
          borderTop: '1px solid rgba(0,0,0,0.14)',
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          gap: '16px',
        }}
      >
        <div>
          <p
            style={{
              fontSize: '11px',
              letterSpacing: '0.2em',
              color: '#666666',
              textTransform: 'uppercase',
              marginBottom: '6px',
            }}
          >
            {course.category}
          </p>
          <p
            style={{
              fontSize: '18px',
              fontWeight: 500,
              color: '#000000',
              letterSpacing: '-0.01em',
              lineHeight: 1.3,
            }}
          >
            {course.title}
          </p>
          <p className="course-card__author">
            Автор: {course.owner?.name || course.owner?.email || course.author}
          </p>
        </div>
        <span
          className="course-card__link"
          style={{
            fontSize: '12px',
            letterSpacing: '0.14em',
            color: '#000000',
            textTransform: 'uppercase',
            whiteSpace: 'nowrap',
          }}
        >
          Смотреть →
        </span>
      </div>
    </button>
  )

  if (isMobile) {
    return <div className="work-item course-card is-static-mobile">{content}</div>
  }

  return (
    <TiltCard
      tiltLimit={8}
      scale={1.015}
      perspective={1200}
      effect="evade"
      spotlight
      className="work-item course-card"
    >
      {content}
    </TiltCard>
  )
}
