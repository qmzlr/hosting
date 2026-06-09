import { router } from '@inertiajs/react'
import Header from './sections/Header'
import Hero from './sections/Hero'
import Philosophy from './sections/Philosophy'
import Works from './sections/Works'
import Capabilities from './sections/Capabilities'
import Spatial from './sections/Spatial'
import { PlayNoteFooter } from './components/PlayNoteFooter'
import Preloader from './sections/Preloader'
import Benefits from './sections/Benefits'
import type { Course } from './data/courses'

export default function App({ courses = [] }: { courses?: Course[] }) {
  const handleSelectCourse = (id: string) => router.visit(`/courses/${id}`)

  return (
    <>
      <Preloader />
      <Header />
      <main>
        <Spatial />
        <Philosophy />
        <Works courses={courses} onSelectCourse={handleSelectCourse} />
        <Capabilities />
        <Benefits />
        <Hero />
      </main>
      <PlayNoteFooter />
    </>
  )
}
