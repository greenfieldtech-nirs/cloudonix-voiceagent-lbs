import React from 'react';

export const Pricing: React.FC = () => {
  const plans = [
    {
      name: 'Starter',
      price: '$49',
      period: '/month',
      description: 'Perfect for getting started with voice applications',
      features: [
        'Up to 1,000 voice calls/month',
        'Basic IVR functionality',
        'Email support',
        'Basic analytics dashboard',
        '1 phone number included'
      ],
      buttonText: 'Start Free Trial',
      buttonVariant: 'secondary' as const
    },
    {
      name: 'Professional',
      price: '$149',
      period: '/month',
      description: 'Advanced features for growing businesses',
      popular: true,
      features: [
        'Up to 10,000 voice calls/month',
        'AI-powered conversations',
        'Priority phone support',
        'Advanced analytics & reporting',
        '5 phone numbers included',
        'Custom integrations',
        'API access'
      ],
      buttonText: 'Get Started',
      buttonVariant: 'primary' as const
    },
    {
      name: 'Enterprise',
      price: 'Custom',
      period: '',
      description: 'Tailored solutions for large organizations',
      features: [
        'Unlimited voice calls',
        'Full AI suite integration',
        'Dedicated account manager',
        'Custom SLA guarantees',
        'Unlimited phone numbers',
        'White-label solution',
        'On-premise deployment'
      ],
      buttonText: 'Contact Sales',
      buttonVariant: 'secondary' as const
    }
  ];

  return (
    <section id="pricing" className="py-20 bg-white">
      <div className="container mx-auto px-4 sm:px-6 lg:px-8">
        {/* Section header */}
        <div className="text-center max-w-3xl mx-auto mb-16">
          <h2 className="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">
            Simple, Transparent Pricing
          </h2>
          <p className="text-lg text-gray-600">
            Choose the plan that fits your needs. Start free and scale as you grow.
            All plans include our core voice platform and 24/7 monitoring.
          </p>
        </div>

        {/* Pricing cards */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 max-w-7xl mx-auto">
          {plans.map((plan, index) => (
            <div key={index} className={`relative ${plan.popular ? 'lg:-mt-4' : ''}`}>
              {/* Popular badge */}
              {plan.popular && (
                <div className="absolute -top-4 left-1/2 transform -translate-x-1/2">
                  <span className="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-4 py-2 rounded-full text-sm font-semibold shadow-lg">
                    Most Popular
                  </span>
                </div>
              )}

              {/* Card */}
              <div className={`bg-white rounded-2xl shadow-lg border-2 transition-all duration-300 hover:shadow-xl ${
                plan.popular ? 'border-indigo-500 shadow-indigo-100' : 'border-gray-100 hover:border-gray-200'
              }`}>
                <div className="p-8">
                  {/* Header */}
                  <div className="text-center mb-8">
                    <h3 className="text-2xl font-bold text-gray-900 mb-2">
                      {plan.name}
                    </h3>
                    <p className="text-gray-600 mb-4">
                      {plan.description}
                    </p>

                    {/* Price */}
                    <div className="flex items-baseline justify-center">
                      <span className="text-4xl font-bold text-gray-900">
                        {plan.price}
                      </span>
                      <span className="text-gray-600 ml-1">
                        {plan.period}
                      </span>
                    </div>
                  </div>

                  {/* Features */}
                  <ul className="space-y-4 mb-8">
                    {plan.features.map((feature, featureIndex) => (
                      <li key={featureIndex} className="flex items-start">
                        <svg className="w-5 h-5 text-green-500 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                          <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                        </svg>
                        <span className="text-gray-600">{feature}</span>
                      </li>
                    ))}
                  </ul>

                  {/* CTA Button */}
                  <button className={`w-full py-3 px-6 rounded-lg font-semibold transition-all duration-200 ${
                    plan.buttonVariant === 'primary'
                      ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white hover:shadow-xl hover:shadow-indigo-500/25 transform hover:-translate-y-1'
                      : 'border-2 border-gray-300 text-gray-700 hover:border-indigo-500 hover:text-indigo-600'
                  }`}>
                    {plan.buttonText}
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Bottom note */}
        <div className="text-center mt-12">
          <p className="text-gray-600">
            All plans include 14-day free trial. No credit card required.
            <br />
            <a href="#contact" className="text-indigo-600 hover:text-indigo-700 font-medium">
              Need a custom plan? Contact our sales team →
            </a>
          </p>
        </div>
      </div>
    </section>
  );
};