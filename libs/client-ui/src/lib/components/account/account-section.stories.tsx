import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { AccountSection } from './account-section';

const Story: ComponentMeta<typeof AccountSection> = {
  component: AccountSection,
  title: 'AccountSection',
};
export default Story;

const Template: ComponentStory<typeof AccountSection> = (args) => <AccountSection {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
