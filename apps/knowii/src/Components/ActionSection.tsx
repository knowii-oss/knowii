import type { PropsWithChildren } from 'react';
import SectionTitle from '@/Components/SectionTitle';
import { Card } from '@/Components/ui';

interface Props {
  title: string;
  description: string;
}

export default function ActionSection({ title, description, children }: PropsWithChildren<Props>) {
  return (
    <div className="md:grid md:grid-cols-3 md:gap-6">
      <SectionTitle title={title} description={description} />

      <div className="mt-5 md:col-span-2 md:mt-0">
        <Card as="div" className="px-4 py-5 sm:p-6">
          {children}
        </Card>
      </div>
    </div>
  );
}
