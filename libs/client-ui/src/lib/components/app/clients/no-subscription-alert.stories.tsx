import type { ComponentStory, ComponentMeta } from '@storybook/react';
import NoSubscriptionAlert from './no-subscription-alert';

const Story: ComponentMeta<typeof NoSubscriptionAlert> = {
  component: NoSubscriptionAlert,
  title: 'NoSubscriptionAlert',
};
export default Story;

const Template: ComponentStory<typeof NoSubscriptionAlert> = (args) => <NoSubscriptionAlert {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
