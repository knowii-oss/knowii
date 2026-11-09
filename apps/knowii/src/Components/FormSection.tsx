import type { FormEventHandler, PropsWithChildren, ReactNode } from 'react';
import SectionTitle from '@/Components/SectionTitle';
import { Card, cn } from '@/Components/ui';

interface Props {
  title: string;
  description: string | ReactNode;
  renderActions?(): ReactNode;
  onSubmit: FormEventHandler;
}

export default function FormSection({ onSubmit, renderActions, title, description, children }: PropsWithChildren<Props>) {
  const hasActions = !!renderActions;

  return (
    <div className="md:grid md:grid-cols-3 md:gap-6">
      <SectionTitle title={title} description={description} />

      <div className="mt-5 md:col-span-2 md:mt-0">
        <Card as="div" className="overflow-hidden">
          <form onSubmit={onSubmit}>
            <div className={cn('px-4 py-5 sm:p-6')}>
              <div className="grid grid-cols-6 gap-6">{children}</div>
            </div>

            {hasActions && (
              <div className="flex flex-row items-center justify-end border-t border-border bg-overlay/5 px-4 py-3 sm:px-6">
                {renderActions?.()}
              </div>
            )}
          </form>
        </Card>
      </div>
    </div>
  );
}
