import React from 'react';

export const Features: React.FC = () => {
  const features = [
    {
      icon: '🎙️',
      title: 'AI-Powered Voice',
      description: 'Advanced natural language processing for human-like conversations and intelligent call routing.',
      color: 'from-indigo-500 to-purple-600'
    },
    {
      icon: '⚡',
      title: 'Lightning Fast',
      description: 'Sub-second response times with global CDN and Redis-powered caching for optimal performance.',
      color: 'from-yellow-500 to-orange-600'
    },
    {
      icon: '🛡️',
      title: 'Enterprise Security',
      description: 'Bank-grade encryption, SOC 2 compliance, and multi-tenant isolation for complete data security.',
      color: 'from-green-500 to-emerald-600'
    },
    {
      icon: '📊',
      title: 'Real-Time Analytics',
      description: 'Comprehensive dashboards, call recordings, and performance metrics to optimize your voice applications.',
      color: 'from-blue-500 to-cyan-600'
    },
    {
      icon: '🔄',
      title: 'Auto-Scaling',
      description: 'Automatically scale from hundreds to millions of concurrent calls based on demand.',
      color: 'from-purple-500 to-pink-600'
    },
    {
      icon: '🌐',
      title: 'Global Infrastructure',
      description: 'Deploy voice applications worldwide with 99.9% uptime SLA and global redundancy.',
      color: 'from-red-500 to-rose-600'
    }
  ];

  return (
    <section id="features" className="py-20 bg-white">
      <div className="container mx-auto px-4 sm:px-6 lg:px-8">
        {/* Section header */}
        <div className="text-center max-w-3xl mx-auto mb-16">
          <h2 className="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">
            Powerful Features for
            <span className="block bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
              Voice Innovation
            </span>
          </h2>
          <p className="text-lg text-gray-600">
            Everything you need to build, deploy, and scale intelligent voice applications.
            From AI conversations to enterprise-grade security.
          </p>
        </div>

        {/* Features grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          {features.map((feature, index) => (
            <div key={index} className="group relative">
              {/* Background gradient on hover */}
              <div className="absolute -inset-1 bg-gradient-to-r opacity-0 group-hover:opacity-100 blur transition duration-300 rounded-2xl" />

              {/* Card content */}
              <div className="relative bg-white border border-gray-200 rounded-2xl p-8 hover:shadow-xl transition-all duration-300">
                {/* Icon */}
                <div className={`inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-r ${feature.color} text-white text-2xl mb-6 shadow-lg`}>
                  {feature.icon}
                </div>

                {/* Content */}
                <h3 className="text-xl font-semibold text-gray-900 mb-3">
                  {feature.title}
                </h3>
                <p className="text-gray-600 leading-relaxed">
                  {feature.description}
                </p>

                {/* Hover indicator */}
                <div className="absolute bottom-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                  <svg className="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                  </svg>
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Bottom CTA */}
        <div className="text-center mt-16">
          <p className="text-gray-600 mb-6">
            Ready to transform your voice communications?
          </p>
          <button className="inline-flex items-center px-8 py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold rounded-lg hover:shadow-xl hover:shadow-indigo-500/25 transition-all duration-200 transform hover:-translate-y-1">
            Explore All Features
            <svg className="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
          </button>
        </div>
      </div>
    </section>
  );
};