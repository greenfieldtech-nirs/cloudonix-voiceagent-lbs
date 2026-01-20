import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';

export const Navbar: React.FC = () => {
  const [isOpen, setIsOpen] = useState(false);
  const navigate = useNavigate();

  const isAuthenticated = () => {
    return localStorage.getItem('token') !== null;
  };

  const handleLogout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    navigate('/');
  };

  return (
    <header className="fixed w-full z-50 bg-white/95 backdrop-blur-sm border-b border-gray-200">
      <nav className="container mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center py-4">
          {/* Logo */}
          <div className="flex items-center">
            <span className="text-2xl mr-2">🎙️</span>
            <span className="text-xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
              AI Voice SaaS
            </span>
          </div>

          {/* Desktop Navigation */}
          <div className="hidden md:flex items-center space-x-8">
            <a href="#features" className="text-gray-600 hover:text-indigo-600 transition-colors">
              Features
            </a>
            <a href="#pricing" className="text-gray-600 hover:text-indigo-600 transition-colors">
              Pricing
            </a>
            <a href="#testimonials" className="text-gray-600 hover:text-indigo-600 transition-colors">
              Testimonials
            </a>
            <a href="#contact" className="text-gray-600 hover:text-indigo-600 transition-colors">
              Contact
            </a>

            {isAuthenticated() ? (
              <>
                <Link to="/admin" className="text-gray-600 hover:text-indigo-600 px-4 py-2 rounded-lg transition-colors">
                  Dashboard
                </Link>
                <button
                  onClick={handleLogout}
                  className="text-gray-600 hover:text-indigo-600 px-4 py-2 rounded-lg transition-colors"
                >
                  Sign Out
                </button>
              </>
            ) : (
              <>
                <Link to="/login" className="text-gray-600 hover:text-indigo-600 px-4 py-2 rounded-lg transition-colors">
                  Sign In
                </Link>
                <Link to="/register" className="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-2 rounded-lg hover:shadow-lg transition-all">
                  Get Started
                </Link>
              </>
            )}
          </div>

          {/* Mobile menu button */}
          <div className="md:hidden">
            <button
              onClick={() => setIsOpen(!isOpen)}
              className="text-gray-600 hover:text-gray-900"
            >
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={isOpen ? "M6 18L18 6M6 6l12 12" : "M4 6h16M4 12h16M4 18h16"} />
              </svg>
            </button>
          </div>
        </div>

        {/* Mobile Navigation */}
        {isOpen && (
          <div className="md:hidden">
            <div className="px-2 pt-2 pb-3 space-y-1 bg-white border-t border-gray-200">
              <a href="#features" className="block px-3 py-2 text-gray-600 hover:text-indigo-600 transition-colors">
                Features
              </a>
              <a href="#pricing" className="block px-3 py-2 text-gray-600 hover:text-indigo-600 transition-colors">
                Pricing
              </a>
              <a href="#testimonials" className="block px-3 py-2 text-gray-600 hover:text-indigo-600 transition-colors">
                Testimonials
              </a>
               <a href="#contact" className="block px-3 py-2 text-gray-600 hover:text-indigo-600 transition-colors">
                 Contact
               </a>

               {isAuthenticated() ? (
                 <div className="px-3 py-2 space-y-2">
                   <Link to="/admin" className="block w-full text-left text-gray-600 hover:text-indigo-600 transition-colors">
                     Dashboard
                   </Link>
                   <button
                     onClick={handleLogout}
                     className="block w-full text-left text-gray-600 hover:text-indigo-600 transition-colors"
                   >
                     Sign Out
                   </button>
                 </div>
               ) : (
                 <div className="px-3 py-2 space-y-2">
                   <Link to="/login" className="block w-full text-left text-gray-600 hover:text-indigo-600 transition-colors">
                     Sign In
                   </Link>
                   <Link to="/register" className="block w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-4 py-2 rounded-lg hover:shadow-lg transition-all text-center">
                     Get Started
                   </Link>
                 </div>
               )}
            </div>
          </div>
        )}
      </nav>
    </header>
  );
};