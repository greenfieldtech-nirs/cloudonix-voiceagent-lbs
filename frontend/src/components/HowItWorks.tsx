import React from 'react';

export const HowItWorks: React.FC = () => {
  const steps = [
    {
      step: '01',
      title: 'Connect Your Numbers',
      description: 'Integrate your existing phone numbers or get new ones through our Cloudonix partnership.',
      icon: '📱',
      details: ['API integration', 'Number porting', 'Instant activation']
    },
    {
      step: '02',
      title: 'Design Voice Flows',
      description: 'Use our visual CXML builder or code custom voice applications with AI capabilities.',
      icon: '🎨',
      details: ['Drag & drop builder', 'AI conversation flows', 'Custom scripting']
    },
    {
      step: '03',
      title: 'Deploy & Scale',
      description: 'Launch your voice applications globally with automatic scaling and monitoring.',
      icon: '🚀',
      details: ['Global deployment', 'Auto-scaling', 'Real-time monitoring']
    },
    {
      step: '04',
      title: 'Analyze & Optimize',
      description: 'Track performance, analyze conversations, and continuously improve your voice experience.',
      icon: '📊',
      details: ['Performance analytics', 'Call recordings', 'AI insights']
    }
  ];

  return (
    <section className="py-20 bg-gray-50">
      <div className="container mx-auto px-4 sm:px-6 lg:px-8">
        {/* Section header */}
        <div className="text-center max-w-3xl mx-auto mb-16">
          <h2 className="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">
            How AI Voice SaaS Works
          </h2>
          <p className="text-lg text-gray-600">
            Get started in minutes with our streamlined process.
            From setup to deployment, we make voice innovation simple.
          </p>
        </div>

        {/* Steps */}
        <div className="relative">
          {/* Connection line */}
          <div className="hidden lg:block absolute top-24 left-1/2 transform -translate-x-1/2 w-full max-w-4xl">
            <div className="flex justify-between">
              {steps.slice(0, -1).map((_, index) => (
                <div key={index} className="flex-1 h-0.5 bg-gradient-to-r from-indigo-200 to-purple-200 mx-8 mt-8"></div>
              ))}
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-4 gap-8 lg:gap-4">
            {steps.map((step, index) => (
              <div key={index} className="relative">
                {/* Step number */}
                <div className="flex items-center justify-center w-16 h-16 bg-gradient-to-r from-indigo-600 to-purple-600 text-white text-xl font-bold rounded-full mb-6 mx-auto shadow-lg">
                  {step.step}
                </div>

                {/* Step content */}
                <div className="text-center lg:text-left">
                  <div className="text-4xl mb-4">{step.icon}</div>
                  <h3 className="text-xl font-semibold text-gray-900 mb-3">
                    {step.title}
                  </h3>
                  <p className="text-gray-600 mb-4">
                    {step.description}
                  </p>

                  {/* Details */}
                  <ul className="text-sm text-gray-500 space-y-1">
                    {step.details.map((detail, detailIndex) => (
                      <li key={detailIndex} className="flex items-center justify-center lg:justify-start">
                        <svg className="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                          <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                        </svg>
                        {detail}
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Bottom CTA */}
        <div className="text-center mt-16">
          <div className="bg-white rounded-2xl shadow-lg p-8 max-w-2xl mx-auto border border-gray-100">
            <h3 className="text-2xl font-bold text-gray-900 mb-4">
              Ready to Get Started?
            </h3>
            <p className="text-gray-600 mb-6">
              Join thousands of businesses already using AI Voice SaaS to transform their voice communications.
            </p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <button className="px-8 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold rounded-lg hover:shadow-xl transition-all duration-200">
                Start Building Now
              </button>
              <button className="px-8 py-3 border-2 border-gray-300 text-gray-700 font-semibold rounded-lg hover:border-indigo-500 hover:text-indigo-600 transition-colors duration-200">
                Contact Sales
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};