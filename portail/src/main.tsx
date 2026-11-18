import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import './index.css'
import App from './App.tsx'
import { initialiserTheme } from './components/BoutonTheme.tsx'
import { ValidationProvider } from './components/Validation.tsx'
import { Demarrage } from './layout/Demarrage.tsx'
import { AuthProvider } from './lib/auth.tsx'

initialiserTheme()

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <BrowserRouter>
      <AuthProvider>
        <Demarrage>
          <ValidationProvider>
            <App />
          </ValidationProvider>
        </Demarrage>
      </AuthProvider>
    </BrowserRouter>
  </StrictMode>,
)
