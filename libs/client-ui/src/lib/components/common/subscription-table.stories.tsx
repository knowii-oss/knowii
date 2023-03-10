import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { SubscriptionTable } from './subscription-table';

const Story: ComponentMeta<typeof SubscriptionTable> = {
  component: SubscriptionTable,
  title: 'SubscriptionTable',
};
export default Story;

const Template: ComponentStory<typeof SubscriptionTable> = (args) => <SubscriptionTable {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
