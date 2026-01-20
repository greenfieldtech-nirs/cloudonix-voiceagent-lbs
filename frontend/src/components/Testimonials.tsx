import React from 'react';

export const Testimonials: React.FC = () => {
  const testimonials = [
    {
      quote: "AI Voice SaaS transformed our customer service operations. The AI-powered IVR system handles 80% of our calls automatically, and the remaining calls go to the right department instantly.",
      author: "Sarah Chen",
      title: "CTO, TechCorp Solutions",
      avatar: "SC",
      rating: 5
    },
    {
      quote: "The integration with Cloudonix was seamless, and the multi-tenant architecture allows us to serve multiple clients securely. Outstanding performance and reliability.",
      author: "Marcus Rodriguez",
      title: "VP of Engineering, VoiceFirst Inc",
      avatar: "MR",
      rating: 5
    },
    {
      quote: "We've reduced our call handling costs by 60% while improving customer satisfaction scores. The analytics dashboard gives us deep insights into call patterns and performance.",
      author: "Emily Watson",
      title: "Operations Director, CallCenter Pro",
      avatar: "EW",
      rating: 5
    }
  ];

  return (
    <section id="testimonials" className="py-20 bg-gradient-to-br from-slate-50 to-indigo-50">
      <div className="container mx-auto px-4 sm:px-6 lg:px-8">
        {/* Section header */}
        <div className="text-center max-w-3xl mx-auto mb-16">
          <h2 className="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">
            Loved by Developers & Businesses
          </h2>
          <p className="text-lg text-gray-600">
            See what our customers say about building voice applications with AI Voice SaaS.
          </p>
        </div>

        {/* Testimonials grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          {testimonials.map((testimonial, index) => (
            <div key={index} className="bg-white rounded-2xl p-8 shadow-lg border border-gray-100 hover:shadow-xl transition-shadow duration-300">
              {/* Rating */}
              <div className="flex mb-4">
                {[...Array(testimonial.rating)].map((_, i) => (
                  <svg key={i} className="w-5 h-5 text-yellow-400 fill-current" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                  </svg>
                ))}
              </div>

              {/* Quote */}
              <blockquote className="text-gray-600 mb-6 leading-relaxed">
                "{testimonial.quote}"
              </blockquote>

              {/* Author */}
              <div className="flex items-center">
                <div className="w-12 h-12 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold mr-4">
                  {testimonial.avatar}
                </div>
                <div>
                  <div className="font-semibold text-gray-900">
                    {testimonial.author}
                  </div>
                  <div className="text-gray-600 text-sm">
                    {testimonial.title}
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Trust indicators */}
        <div className="mt-16 text-center">
          <p className="text-gray-600 mb-8">
            Trusted by leading companies worldwide
          </p>
          <div className="flex flex-wrap justify-center items-center gap-8 opacity-60">
            <div className="text-2xl font-bold text-gray-400">TechCorp</div>
            <div className="text-2xl font-bold text-gray-400">VoiceFirst</div>
            <div className="text-2xl font-bold text-gray-400">CallCenter Pro</div>
            <div className="text-2xl font-bold text-gray-400">CloudCom</div>
            <div className="text-2xl font-bold text-gray-400">VoiceTech</div>
          </div>
        </div>
      </div>
    </section>
  );
};