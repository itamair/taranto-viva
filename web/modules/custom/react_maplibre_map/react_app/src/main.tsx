import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './css/index.css'
import App from './App.tsx'

createRoot(document.getElementById('react_maplibre_map')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
